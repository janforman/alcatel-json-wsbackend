<?php
require('config.php');

// skill list
$slist[100] = 'Plzen';
$slist[101] = 'Kralovice';
$slist[102] = 'Prestice';
$slist[103] = 'Rokycany';
$slist[104] = 'Klatovy';
$slist[105] = 'Domazlice';
$slist[106] = 'Tachov';
$slist[107] = 'HorsovskyTyn';
$slist[108] = 'Horazdovice';
$slist[109] = 'Susice';
$slist[110] = 'Blovice';
$slist[111] = 'Nepomuk';
$slist[112] = 'Stod';
$slist[113] = 'Nyrany';
$slist[114] = 'Stribro';
$slist[115] = 'PCO';
$slist[999] = 'Paleni';
$GLOBALS['JSESSIONIDEXP'] = 0;

/////////////////// Functions
// Encode ws-text
function encodews($text)
{

    $b = 129; // FIN + text frame
    $len = strlen($text);
    if ($len < 126) {
        return pack('CC', $b, $len) . $text;
    } elseif ($len < 65536) {
        return pack('CCn', $b, 126, $len) . $text;
    } else {
        return pack('CCNN', $b, 127, 0, $len) . $text;
    }
}

// Alcatel - login
function getAlcUserId()
{
    $endpoint = $GLOBALS['alcurl'] . '/api/rest/authenticate?version=1.0';
    $context = stream_context_create(["http" => ["header" => "Authorization: Basic " . $GLOBALS['basicauth']], "ssl" => array("verify_peer" => false, "verify_peer_name" => false)]);
    $json = json_decode(file_get_contents($endpoint, false, $context), true);
    return ($json['credential']);
}

// Alcatel - get JSESSIONID
function getSession($cookie)
{
    $endpoint = $GLOBALS['alcurl'] . '/api/rest/1.0/sessions';
    $authpost = '{"applicationName":"Wallboard"}';
    $context = stream_context_create(["http" => ['method' => 'POST', "header" => "Cookie: AlcUserId=" . $cookie . "\r\n" . "Content-type: application/json\r\n" . 'Content-Length: ' . strlen($authpost) . "\r\n", 'content' => $authpost], "ssl" => array("verify_peer" => false, "verify_peer_name" => false)]);

    $json = file_get_contents($endpoint, false, $context);

    $cookies = array();
    foreach ($http_response_header as $hdr) {
        if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
            parse_str($matches[1], $tmp);
            $cookies += $tmp;
        }
    }
    $GLOBALS['JSESSIONIDEXP'] = time() + 1700;
    return $cookies['JSESSIONID'];
}

// Alcatel - get rest function
function getAlcatel($url, $JSESSIONID, $AlcUserId, $loginName)
{
    $endpoint = $GLOBALS['alcurl'] . '/api/rest/1.0' . $url . '?loginName=' . $loginName;
    $context = stream_context_create(["http" => ['method' => 'GET', "header" => "Cookie: JSESSIONID=" . $JSESSIONID . "; AlcUserId=" . $AlcUserId], "ssl" => array("verify_peer" => false, "verify_peer_name" => false)]);
    return (@file_get_contents($endpoint, false, $context));
}
/////////////////// Functions


// APP INIT - Create WebSocket
//$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
$server = socket_create(AF_INET, SOCK_STREAM, 0);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, $address, $port);
socket_listen($server, 5);

$client = socket_accept($server);

// Send WebSocket handshake headers.

socket_recv($client, $request, 2048, 0);
preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
$key = base64_encode(pack(
    'H*',
    sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
));
$headers = "HTTP/1.1 101 Switching Protocols\r\n";
$headers .= "Upgrade: websocket\r\n";
$headers .= "Connection: Upgrade\r\n";
$headers .= "Sec-WebSocket-Version: 13\r\n";
$headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
socket_write($client, $headers, strlen($headers));

$AlcUserId = getAlcUserId();
$JSESSIONID = getSession($AlcUserId);

// Infinite loop - get data and send it to WebSocket
while (true) {
    sleep(3);

    // session expired - get new
    if ($GLOBALS['JSESSIONIDEXP'] < time()) {
        $AlcUserId = getAlcUserId();
        $JSESSIONID = getSession($AlcUserId);
    }
    $readyagents = 0;
    unset($activeSkill);
    foreach ($numbers as $loginName) {
        $activecalls = json_decode(getAlcatel('/telephony/calls',  $JSESSIONID, $AlcUserId, $loginName), true);
        if ($activecalls == false) continue; // ops read failure continue on next number
        $agentconfig = json_decode(getAlcatel('/acd/agent/config',  $JSESSIONID, $AlcUserId, $loginName), true);

        // total ready agents
        if ($activecalls['calls'] == array()) $readyagents++;

        foreach ($agentconfig['skills']['skills'] as $value) {
            $skill = $value['number'];
            $skill_active = $value['active'];
            if ($skill_active == 1) $skill_active = 1;
            else $skill_active = 0;

            // agent ready
            if ($activecalls['calls'] == array() and $skill_active == 1) {
                $ready = 1;
            } else $ready = 0;

            // available agents, total agents, activated agents
            $activeSkill[$skill] = @array($activeSkill[$skill][0] + $ready, $activeSkill[$skill][1] + 1, $activeSkill[$skill][2] + $skill_active);
        }
        // loop
    }
    // Alcatel connector

    $json = array();
    foreach ($slist as $key => $value) {
        @$json[$value] = '"inqueue" : 0,"logged" : ' . $activeSkill[$key][2] . ',"available" : ' . $activeSkill[$key][0];
    }

    // send to websocket
    $content = '
    {
        "event" : "hasici",
        "data" : {
           "alles" : [
              {"name" : "HZSPK","inqueue" : 0,"logged" : ' . count($numbers) . ',"available" : ' . $readyagents . ',"sortindex" : 61},
              {"name" : "PCO",' . $json['PCO'] . ',"sortindex" : 62},
              {"name" : "Paleni","inqueue" : 0,"logged" : 0,"available" : 0,"sortindex" : 63}
           ],
           "plzen" : [
              {"name" : "Blovice",' . $json['Blovice'] . ',"sortindex" : 11},
              {"name" : "Kralovice",' . $json['Kralovice'] . ',"sortindex" : 12},
              {"name" : "Nepomuk",' . $json['Nepomuk'] . ',"sortindex" : 13},
              {"name" : "Nyrany",' . $json['Nyrany'] . ',"sortindex" : 14},
              {"name" : "Plzen",' . $json['Plzen'] . ',"sortindex" : 15},
              {"name" : "Prestice",' . $json['Prestice'] . ',"sortindex" : 16},
              {"name" : "Stod",' . $json['Stod'] . ',"sortindex" : 17}
           ],
           "rokycany" : [
              {"name" : "Rokycany",' . $json['Rokycany'] . ',"sortindex" : 21}
           ],
           "domazlice" : [
              {"name" : "Domazlice",' . $json['Domazlice'] . ',"sortindex" : 31 },
              {"name" : "HorsovskyTyn",' . $json['HorsovskyTyn'] . ',"sortindex" : 32}
           ],
           "tachov" : [
              {"name" : "Tachov",' . $json['Tachov'] . ',"sortindex" : 41},
              {"name" : "Stribro",' . $json['Stribro'] . ',"sortindex" : 42}
           ],
           "klatovy" : [
              {"name" : "Horazdovice",' . $json['Horazdovice'] . ',"sortindex" : 51},
              {"name" : "Klatovy",' . $json['Klatovy'] . ',"sortindex" : 52},
              {"name" : "Susice",' . $json['Susice'] . ',"sortindex" : 53}
           ]
        }
     }
';
    // json ws feed
    $response = encodews($content);
    @socket_write($client, $response, strlen($response));

    // rcs as status
    sleep(1);
    $rcs = '{"event":"RCS","data":{"rcsstatus":"OK"}}';
    $response = encodews($rcs);
    @socket_write($client, $response, strlen($response));

    sleep(1);
}
