<?php
ini_set('default_socket_timeout', 3);
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
$slist[117] = 'Paleni';
$GLOBALS['JSESSIONIDEXP'] = 0;

function hybi10Decode($data)
{
    $bytes = $data;
    $dataLength = '';
    $mask = '';
    $coded_data = '';
    $decodedData = '';
    $secondByte = sprintf('%08b', ord($bytes[1]));
    $masked = ($secondByte[0] == '1') ? true : false;
    $dataLength = ($masked === true) ? ord($bytes[1]) & 127 : ord($bytes[1]);

    if ($masked === true) {
        if ($dataLength === 126) {
            $mask = substr($bytes, 4, 4);
            $coded_data = substr($bytes, 8);
        } elseif ($dataLength === 127) {
            $mask = substr($bytes, 10, 4);
            $coded_data = substr($bytes, 14);
        } else {
            $mask = substr($bytes, 2, 4);
            $coded_data = substr($bytes, 6);
        }
        for ($i = 0; $i < strlen($coded_data); $i++) {
            $decodedData .= $coded_data[$i] ^ $mask[$i % 4];
        }
    } else {
        if ($dataLength === 126) {
            $decodedData = substr($bytes, 4);
        } elseif ($dataLength === 127) {
            $decodedData = substr($bytes, 10);
        } else {
            $decodedData = substr($bytes, 2);
        }
    }
    return $decodedData;
}

function hybi10Encode($payload, $type = 'text', $masked = true)
{
    $frameHead = array();
    $frame = '';
    $payloadLength = strlen($payload);

    switch ($type) {
        case 'text':
            // first byte indicates FIN, Text-Frame (10000001):
            $frameHead[0] = 129;
            break;

        case 'close':
            // first byte indicates FIN, Close Frame(10001000):
            $frameHead[0] = 136;
            break;

        case 'ping':
            // first byte indicates FIN, Ping frame (10001001):
            $frameHead[0] = 137;
            break;

        case 'pong':
            // first byte indicates FIN, Pong frame (10001010):
            $frameHead[0] = 138;
            break;
    }

    // set mask and payload length (using 1, 3 or 9 bytes)
    if ($payloadLength > 65535) {
        $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
        $frameHead[1] = ($masked === true) ? 255 : 127;
        for ($i = 0; $i < 8; $i++) {
            $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
        }

        // most significant bit MUST be 0 (close connection if frame too big)
        if ($frameHead[2] > 127) {
            return false;
        }
    } elseif ($payloadLength > 125) {
        $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
        $frameHead[1] = ($masked === true) ? 254 : 126;
        $frameHead[2] = bindec($payloadLengthBin[0]);
        $frameHead[3] = bindec($payloadLengthBin[1]);
    } else {
        $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
    }

    // convert frame-head to string:
    foreach (array_keys($frameHead) as $i) {
        $frameHead[$i] = chr($frameHead[$i]);
    }

    if ($masked === true) {
        // generate a random mask:
        $mask = array();
        for ($i = 0; $i < 4; $i++) {
            $mask[$i] = chr(rand(0, 255));
        }

        $frameHead = array_merge($frameHead, $mask);
    }
    $frame = implode('', $frameHead);
    // append payload to frame:
    for ($i = 0; $i < $payloadLength; $i++) {
        $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
    }
    return $frame;
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
$client = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!@socket_connect($client, $address, $port)) exit;

// Send WebSocket handshake headers.
$key = base64_encode(random_bytes(16));
$headers = "GET / HTTP/1.1\r\n";
$headers .= "Upgrade: websocket\r\n";
$headers .= "Connection: Upgrade\r\n";
$headers .= "Sec-WebSocket-Version: 13\r\n";
$headers .= "Sec-WebSocket-Key: $key\r\n";

socket_write($client, $headers, strlen($headers));

// Login to ROXE
$AlcUserId = getAlcUserId();
$JSESSIONID = getSession($AlcUserId);

// Infinite loop - get data and send it to WebSocket
while (true) {
    sleep(2);

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

        if ($agentconfig) {
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
        }
        // loop
    }
    // Alcatel connector

    $json = array();
    foreach ($slist as $key => $value) {
        @$json[$value] = '"inqueue" : 0,"logged" : ' . intval($activeSkill[$key][2]) . ',"available" : ' . intval($activeSkill[$key][0]);
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
    $response = hybi10Encode($content);
    @socket_write($client, $response, strlen($response));

    sleep(2);
    // rcs as status
    if (@file_get_contents($GLOBALS['rcsurl'], false)) $rcs = '{"event":"RCS","data":{"rcsstatus":"OK"}}'; else  $rcs = '{"event":"RCS","data":{"rcsstatus":"!!!"}}';
    $response = hybi10Encode($rcs);
    @socket_write($client, $response, strlen($response));
}
