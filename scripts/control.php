<?php

require "../vendor/autoload.php";

use React\EventLoop\Factory as LoopFactory;
use \React\ZMQ\Context as ZmqContext;


$loop = LoopFactory::create();
$context = new ZmqContext($loop);

$scriptCheckResults = $context->getSocket(\ZMQ::SOCKET_PUSH);
$scriptCheckResults->connect("tcp://127.0.0.1:5694");

$sub = $context->getSocket(\ZMQ::SOCKET_SUB);
$sub->connect('tcp://127.0.0.1:5594');
$sub->subscribe('control');
$sub->on('messages', function ($msg) use ($loop) {
    if ($msg[1] == 'shutdown') {
        $loop->stop();
    }
});

$workers = $context->getSocket(\ZMQ::SOCKET_PUSH);
$workers->bind('tcp://127.0.0.1:5592');

$socket = $context->getSocket(\ZMQ::SOCKET_PULL);
$socket->bind("tcp://127.0.0.1:5591");

/**
 * @var \React\Promise\Deferred[]
 */
$deferredSet = [];
$results = [];
$socket->on('message', function ($message) use ($scriptCheckResults, $workers, &$results, &$deferredSet) {
    // Incoming work. Distribute.
    $payload = json_decode($message, true);

    $reqid = $payload['txid'];

    $batch = [];
    $work = [
        "txid" => $payload['txid'],
        "tx" => $payload['tx'],
        "flags" => $payload['flags'],
        "vin" => null,
        "scriptPubKey" => null
    ];

    // Send to workers, and create a Promise for each result.
    foreach ($payload['scripts'] as $vin => $scriptPubKey) {
        $work['vin'] = $vin;
        $work['scriptPubKey'] = $scriptPubKey;
        $deferred = new \React\Promise\Deferred();
        $deferredSet[$payload['txid'].$vin] = $deferred;
        $batch[] = $deferred->promise();
        $workers->send(json_encode($work));
    }

    // Once all promises have resolved, return outcome to socket.
    \React\Promise\all($batch)
        ->then(function ($results) use ($scriptCheckResults, $reqid) {
            $final = true;
            foreach ($results as $result) {
                $final &= $result;
            }
            echo "Send back\n";
            $scriptCheckResults->send(json_encode(['txid'=>$reqid, 'result' => $final]));
        });
});

$results = $context->getSocket(\ZMQ::SOCKET_PULL);
$results->bind("tcp://127.0.0.1:5593");
$results->on('message', function ($message) use (&$deferredSet) {
    echo 'some results';
    echo "\nMessage: $message\n";
    $payload = json_decode($message, true);
    $deferredSet[$payload['txid'].$payload['vin']]->resolve($payload['result']);
});
$loop->run();

