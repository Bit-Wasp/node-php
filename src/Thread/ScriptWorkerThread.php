<?php

namespace BitWasp\Bitcoin\Node\Thread;

use BitWasp\Bitcoin\Script\ScriptFactory;
use \BitWasp\Thread\Thread;
use \React\EventLoop\Factory as LoopFactory;
use \React\ZMQ\Context as ZmqContext;
use BitWasp\Bitcoin\Script\ConsensusFactory;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Buffertools\Buffer;
use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Transaction\TransactionFactory;

class ScriptWorkerThread extends Thread
{
    public function __construct()
    {
        parent::__construct(function () {

            $loop = LoopFactory::create();
            $context = new ZmqContext($loop);

            $control = $context->getSocket(\ZMQ::SOCKET_SUB);
            $control->connect('tcp://127.0.0.1:5594');
            $control->subscribe('control');
            $control->on('messages', function ($msg) use ($loop) {
                if ($msg[1] == 'shutdown') {
                    $loop->stop();
                }
            });

            $this->results = $context->getSocket(\ZMQ::SOCKET_PUSH);
            $this->results->connect("tcp://127.0.0.1:5593");

            $workers = $context->getSocket(\ZMQ::SOCKET_PULL);
            $workers->connect('tcp://127.0.0.1:5592');
            $workers->on('message', function ($message) {
                $details = json_decode($message, true);
                $txid = $details['txid'];
                $flags = $details['flags'];
                $vin = $details['vin'];
                $scriptPubKey = new Script(Buffer::hex($details['scriptPubKey']));
                $tx = TransactionFactory::fromHex($details['tx']);

                $this->results->send(json_encode([
                    'txid' => $txid,
                    'vin' => $vin,
                    'result' => ScriptFactory::consensus(new Flags($flags))->verify($tx, $scriptPubKey, $vin)
                ]));
            });

            $loop->run();

            exit(0);
        });
    }
}