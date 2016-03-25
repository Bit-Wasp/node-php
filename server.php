<?php

require "vendor/autoload.php";

$loop = React\EventLoop\Factory::create();
$server = new \React\Socket\Server($loop);
$server->on('connection', function (\React\Stream\Stream $stream) {
    $stream->on('data', function ($data) use ($stream) {
        if ($data == 'say hi!') {
            echo "client sent somethiing\n";
            echo "msg: $data\n";
            $stream->write('Hi back!');
        }

    });
    echo "Inbound connection: ".PHP_EOL;
    //$stream->write('hello from server!');
});
$server->listen(1234);

$loop->run();