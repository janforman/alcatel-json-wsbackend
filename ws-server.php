<?php
// WebSocket server

require('config.php');

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $address, $port);
socket_listen($socket);
$clients = [$socket];
$null = null;
while (true) {
    $newClientReader = $clients;
    if (socket_select($newClientReader, $null, $null, 12) < 1) continue;
    if (in_array($socket, $newClientReader)) {
        $newClient = socket_accept($socket);
        $clients[] = $newClient;
        echo "Client connected. Total: " . count($clients) - 1 . "\n";
        $header = socket_read($newClient, 4096);
        handshake($newClient, $header);
        $newClientIndex = array_search($socket, $newClientReader);
        unset($newClientReader[$newClientIndex]);
    }
    foreach ($newClientReader as $client) {
        while (@socket_recv($client, $clientData, 4096, 0) >= 1) {
            $message = decodeMessage($clientData);
            sendMessage($clients, $message);
            break 2;
        }
        $clientData = @socket_read($client, 4096, PHP_NORMAL_READ);
        if ($clientData === false) {
            $clientIndex = array_search($client, $clients);
            unset($clients[$clientIndex]);
            echo "Client disconnected. Total: " . count($clients) - 1 . "\n";
        }
    }
}
function sendMessage($clients, $message)
{
    $message = encodeMessage($message);
    foreach ($clients as $client) {
        @socket_write($client, $message, strlen($message));
    }
}
socket_close($socket);

function encodeMessage($socketData)
{
    $b1 = 0x80 | (0x1 & 0x0f);
    $length = strlen($socketData);

    if ($length <= 125)
        $header = pack('CC', $b1, $length);
    elseif ($length > 125 && $length < 65536)
        $header = pack('CCn', $b1, 126, $length);
    elseif ($length >= 65536)
        $header = pack('CCNN', $b1, 127, $length);
    return $header . $socketData;
}
function decodeMessage($socketData)
{
    $length = ord($socketData[1]) & 127;
    if ($length == 126) {
        $masks = substr($socketData, 4, 4);
        $data = substr($socketData, 8);
    } elseif ($length == 127) {
        $masks = substr($socketData, 10, 4);
        $data = substr($socketData, 14);
    } else {
        $masks = substr($socketData, 2, 4);
        $data = substr($socketData, 6);
    }
    $socketData = "";
    for ($i = 0; $i < strlen($data); ++$i) {
        $socketData .= $data[$i] ^ $masks[$i % 4];
    }
    return $socketData;
}

function handshake($client_socket_resource, $received_header)
{
    $headers = array();
    $lines = preg_split("/\r\n/", $received_header);
    foreach ($lines as $line) {
        $line = chop($line);
        if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) $headers[$matches[1]] = $matches[2];
    }
    $secKey = $headers['Sec-WebSocket-Key'];
    $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    $buffer  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
    socket_write($client_socket_resource, $buffer, strlen($buffer));
}
