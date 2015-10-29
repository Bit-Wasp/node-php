<?php

namespace BitWasp\Bitcoin\Node\Routine;

use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chain\UtxoView;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use \ZMQContext as BlockingZmqContext;

class ZmqScriptCheck implements ScriptCheckInterface
{
    /**
     * @var \ZMQSocket
     */
    private $blockingZmq;

    /**
     * @param BlockingZmqContext $zmq
     */
    public function __construct(BlockingZmqContext $zmq)
    {
        $this->blockingZmq = $zmq->getSocket(\ZMQ::SOCKET_REQ);
        $this->blockingZmq->connect("tcp://127.0.0.1:5591");
    }

    /**
     * @param TransactionInterface $tx
     * @param Flags $flags
     * @param array $scripts
     * @return string
     */
    private function dispatch(TransactionInterface $tx, Flags $flags, array $scripts)
    {
        $this->blockingZmq->send(json_encode([
            'txid' => $tx->getTxId()->getHex(),
            'tx' => $tx->getHex(),
            'flags' => $flags->getFlags(),
            'scripts' => $scripts
        ]));

        $result = $this->blockingZmq->recv();
        return (bool) $result;
    }

    /**
     * @param UtxoView $utxoView
     * @param TransactionInterface $tx
     * @param Flags $flags
     * @return bool
     */
    public function check(UtxoView $utxoView, TransactionInterface $tx, Flags $flags)
    {
        $scripts = [];
        foreach ($tx->getInputs() as $input) {
            $scripts[] = $utxoView
                ->fetchByInput($input)
                ->getOutput()
                ->getScript()
                ->getHex();
        }

        return $this->dispatch($tx, $flags, $scripts);
    }
}