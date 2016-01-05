<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\ZMQ\Context;

class ScriptValidationState implements ScriptValidationInterface
{
    /**
     * @var bool
     */
    private $active;

    /**
     * @var array
     */
    private $results = [];

    private $knownResult;

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
     * ScriptValidationState constructor.
     * @param bool $active
     */
    public function __construct($active = true)
    {
        if (!is_bool($active)) {
            throw new \InvalidArgumentException('ScriptValidationState: $active should be bool');
        }

        $this->loop = \React\EventLoop\Factory::create();
        $this->active = $active;
        $context = new Context($this->loop);
        $this->dispatch = $context->getSocket(\ZMQ::SOCKET_PUSH);
        $this->dispatch->connect("tcp://127.0.0.1:5591");

        $this->resultsListener = $context->getSocket(\ZMQ::SOCKET_PULL);
        $this->resultsListener->connect('tcp://127.0.0.1:5694');
        $this->resultsListener->on('message', function ($message) {
            echo "[msg]\n";
            $payload = json_decode($message, true);
            if (isset($payload['txid']) && isset($this->deferredSet[$payload['txid']])) {
                echo "try txid: " . $payload['txid'] . "\n";
                $this->deferredSet[$payload['txid']]->resolve($payload['result']);
            }
        });
    }

    /**
     * @return bool
     */
    public function active()
    {
        return $this->active;
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
     * @return ScriptValidationInterface
     */
    public function queue(UtxoView $utxoView, TransactionInterface $tx, Flags $flags)
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
        $this->results[] = $promise;
        return $this;
    }

    private function earlyCl()
    {
        $this->dispatch->end();
        $this->resultsListener->end();
    }

    private function cl()
    {
        $count = 0;
        $deferred = new Deferred();
        $canceller = function () use ($deferred, &$count) {
            $count++;
            if ($count == 1) {
                $deferred->resolve();
            }
        };

        $this->dispatch->on('end', $canceller);
        $this->resultsListener->on('end', $canceller);

        $deferred->promise()->then(function () {
            $this->loop->stop();
        });

        $this->dispatch->end();
        $this->resultsListener->disconnect('tcp://127.0.0.1:5694');

    }

    /**
     * Function to keep the loop ticking, allowing the results
     * to be returned.
     *
     * @param PromiseInterface $promise
     * @return null
     * @throws null
     */
    private function awaitResults(PromiseInterface $promise)
    {
        $wait = true;
        $resolved = null;
        $exception = null;

        $promise->then(
            function ($c) use (&$resolved, &$wait) {
                $resolved = $c;
                $wait = false;
                $this->cl();

            },
            function ($error) use (&$exception, &$wait) {
                $exception = $error;
                $wait = false;
                $this->cl();

            }
        );

        while ($wait) {
            $this->loop->run();
        }

        echo "Loop finished\n";

        if ($exception instanceof \Exception) {
            throw $exception;
        }

        return $resolved;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function result()
    {

        if ($this->knownResult !== null) {
            $this->earlyCl();
            return $this->knownResult;
        }

        if (0 === count($this->results)) {
            $this->earlyCl();
            return true;
        }

        $outcome = $this->awaitResults(\React\Promise\all($this->results));

        $result = true;
        foreach ($outcome as $r) {
            if ($result === true) {
                $result = $result && $r;
            }
        }

        $this->knownResult = $result;
        $this->results = [];

        return $result;
    }
}
