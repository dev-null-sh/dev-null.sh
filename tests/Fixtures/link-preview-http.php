<?php

declare(strict_types=1);

$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

if ($server === false) {
    fwrite(STDERR, "{$errorMessage} ({$errorCode})\n");

    exit(1);
}

$address = stream_socket_get_name($server, false);
$port = (int) mb_substr(mb_strrchr($address, ':'), 1);

fwrite(STDOUT, "{$port}\n");

$connection = stream_socket_accept($server, 5);

if ($connection === false) {
    exit(1);
}

$request = '';

while (($line = fgets($connection)) !== false) {
    $request .= $line;

    if ($line === "\r\n") {
        break;
    }
}

file_put_contents($argv[1], $request);

$body = "GIF89a\x01\x00\x01\x00\x80\x00\x00".str_repeat('x', 243);
$response = "HTTP/1.1 200 OK\r\nContent-Type: image/gif\r\nContent-Length: ".mb_strlen($body)."\r\nConnection: close\r\n\r\n".$body;

fwrite($connection, $response);

fclose($connection);
fclose($server);
