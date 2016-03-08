<?php

require "vendor/autoload.php";

use React\EventLoop\Factory as LoopFactory;
use \React\ZMQ\Context as ZmqContext;


$loop = LoopFactory::create();
$context = new ZmqContext($loop);
$deferredSet = [];
$results = [];

$workers = $context->getSocket(\ZMQ::SOCKET_REQ);
$workers->bind('tcp://127.0.0.1:5592');
$workers->on('message', function ($message) use (&$deferredSet) {
    $payload = json_decode($message, true);
    $deferredSet[$payload['txid'].$payload['vin']]->resolve($payload['result']);
});


$socket = $context->getSocket(\ZMQ::SOCKET_REP);
$socket->bind("tcp://127.0.0.1:6661");

/**
 * @var \React\Promise\Deferred[]
 */
$socket->on('message', function ($message) use ($socket, $workers, &$results, &$deferredSet) {
    // Incoming work. Distribute.
    $payload = json_decode($message, true);

    $reqid = $payload['id'];

    $batch = [];
    $work = [
        "txid" => null,
        "tx" => null,
        "flags" => $payload['flags'],
        "vin" => null,
        "scriptPubKey" => null
    ];

    // Send to workers, and create a Promise for each result.
    foreach ($payload['txs'] as $t) {
        $work['txid'] = $t['txid'];
        $work['tx'] = $t['tx'];
        foreach ($t['scripts'] as $vin => $scriptPubKey) {
            $work['vin'] = $vin;
            $work['scriptPubKey'] = $scriptPubKey;
            $deferred = new \React\Promise\Deferred();
            $deferredSet[$t['txid'] . $vin] = $deferred;
            $batch[] = $deferred->promise();
            $workers->send(json_encode($work));
        }
    }

    // Once all promises have resolved, return outcome to socket.
    \React\Promise\all($batch)
        ->then(function ($results) use ($socket, $reqid) {
            $final = true;
            foreach ($results as $result) {
                $final &= $result;
            }
            $socket->send(json_encode(['txid'=>$reqid, 'result' => $final]));
        });
});

$loop->run();

