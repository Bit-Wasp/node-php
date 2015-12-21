<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

class ZmqScriptCheck implements ScriptCheckInterface
{

    /**
     * @var EcAdapterInterface
     */
    private $adapter;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var Deferred[]
     */
    private $deferredSet = [];

    /**
     * @var \React\ZMQ\SocketWrapper
     */
    private $resultsListener;

    /**
     * @var \React\ZMQ\SocketWrapper
     */
    private $dispatch;

    /**
     * ScriptCheck constructor.
     * @param EcAdapterInterface $adapter
     * @param \React\ZMQ\Context $context
     * @param LoopInterface $loop
     */
    public function __construct(EcAdapterInterface $adapter, \React\ZMQ\Context $context, LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->adapter = $adapter;

        $this->dispatch = $context->getSocket(\ZMQ::SOCKET_PUSH);
        $this->dispatch->connect("tcp://127.0.0.1:5591");

        $this->resultsListener = $context->getSocket(\ZMQ::SOCKET_PULL);
        $this->resultsListener->bind('tcp://127.0.0.1:5694');
        $this->resultsListener->on('message', function ($message) {
            $payload = json_decode($message, true);
            echo "try txid: " . $payload['txid'] . "\n";
            $this->deferredSet[$payload['txid']]->resolve($payload['result']);
        });
    }

    /**
     * @param TransactionInterface $tx
     * @param Flags $flags
     * @param array $scripts
     * @return \React\Promise\PromiseInterface
     */
    private function dispatch(TransactionInterface $tx, Flags $flags, array $scripts)
    {
        $h = spl_object_hash($tx);
        echo "Sent to " . $h . "\n";

        $a = [
            'txid' => $h,
            'tx' => $tx->getBuffer()->getHex(),
            'flags' => $flags->getFlags(),
            'scripts' => $scripts
        ];
        $s = json_encode($a);
        $this->dispatch->send($s);

        $deferred = new Deferred();
        $this->deferredSet[$h] = $deferred;

        return $deferred->promise();

    }

    /**
     * @param UtxoView $utxoView
     * @param TransactionInterface $tx
     * @param Flags $flags
     * @param ScriptValidationState $scriptCheckState
     * @return ScriptValidationState
     */
    public function check(UtxoView $utxoView, TransactionInterface $tx, Flags $flags, ScriptValidationState $scriptCheckState)
    {
        $scripts = [];
        for ($i = 0, $c = count($tx->getInputs()); $i < $c; $i++) {
            $scripts[] = $utxoView
                ->fetchByInput($tx->getInput($i))
                ->getOutput()
                ->getScript()
                ->getHex();
        }

        $promise = $this->dispatch($tx, $flags, $scripts);
        $scriptCheckState->queue($promise);

    }
}
