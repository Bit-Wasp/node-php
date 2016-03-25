<?php

require "vendor/autoload.php";

$loop = React\EventLoop\Factory::create();


$client = new \Clue\React\Socks\Client('127.0.0.1:9050', $loop);
$client->setResolveLocal(false);
$client->setProtocolVersion(5);
$connector = $client->createConnector();

$connector->create('ijcdlwrjm2shxwju.onion', 1234)
    ->then(function (\React\Stream\Stream $socket) {
        $socket->on('data', function ($msg) {
            echo "Server sent data: " . $msg . PHP_EOL;
        });

        echo "say hi!\n";
        $socket->write('say hi!');
    }, function ($e) {
        echo "error!";
        echo $e->getMessage().PHP_EOL;
    });

$loop->run();