<?php

namespace BitWasp\Bitcoin\Node\Thread;

use BitWasp\Thread\Thread;
use \React\EventLoop\Factory as LoopFactory;
use \React\ZMQ\Context as ZmqContext;

class ScriptCheckThread extends Thread
{
    public function __construct()
    {
        parent::__construct(function () {
            $loop = LoopFactory::create();
            $context = new ZmqContext($loop);

            $socket = $context->getSocket(\ZMQ::SOCKET_REP);
            $socket->bind("tcp://127.0.0.1:5591");

            $workers = $context->getSocket(\ZMQ::SOCKET_PUSH);
            $workers->bind('tcp://127.0.0.1:5592');

            $sub = $context->getSocket(\ZMQ::SOCKET_SUB);
            $sub->connect('tcp://127.0.0.1:5594');
            $sub->subscribe('control');
            $sub->on('messages', function ($msg) use ($loop) {
                if ($msg[1] == 'shutdown') {
                    $loop->stop();
                }
            });

            /**
             * @var \React\Promise\Deferred[]
             */
            $deferredSet = [];
            $results = [];

            $socket->on('message', function ($message) use ($socket, $workers, &$results, &$deferredSet) {
                // Incoming work. Distribute.
                $payload = json_decode($message, true);

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
                    ->then(function ($results) use ($socket) {
                        $final = true;
                        foreach ($results as $result) {
                            $final &= $result['result'];
                        }

                        $socket->send($final);
                    });
            });

            $results = $context->getSocket(\ZMQ::SOCKET_PULL);
            $results->bind("tcp://127.0.0.1:5593");
            $results->on('message', function ($message) use (&$deferredSet) {
                $payload = json_decode($message, true);
                $deferredSet[$payload['txid'].$payload['vin']]->resolve($payload);
            });

            $loop->run();

            exit(0);
        });
    }
}
