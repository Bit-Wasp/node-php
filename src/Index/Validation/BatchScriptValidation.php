<?php

namespace BitWasp\Bitcoin\Node\Index\Validation;

use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Script\Interpreter\InterpreterInterface;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\TransactionInterface;

class BatchScriptValidation implements ScriptValidationInterface
{
    /**
     * @var bool
     */
    private $active;

    /**
     * @var array
     */
    private $results = [];

    /**
     * @var bool
     */
    private $knownResult;

    /**
     * ScriptValidation constructor.
     * @param bool $active
     * @param int $flags
     */
    public function __construct($active = true, $flags = InterpreterInterface::VERIFY_NONE)
    {
        if (!is_bool($active)) {
            throw new \InvalidArgumentException('ScriptValidationState: $active should be bool');
        }

        $this->active = $active;
    }

    /**
     * @return bool
     */
    public function active()
    {
        return $this->active;
    }

    /**
     * @param UtxoView $utxoView
     * @param TransactionInterface $tx
     * @return ScriptValidationInterface
     */
    public function queue(UtxoView $utxoView, TransactionInterface $tx)
    {
        $hex = $tx->getHex();
        $t = [
            'txid' => spl_object_hash($tx),
            'tx' => $hex,
            'scripts' => []
        ];

        for ($i = 0, $c = count($tx->getInputs()); $i < $c; $i++) {
            $output = $utxoView->fetchByInput($tx->getInput($i))->getOutput();
            $witness = isset($tx->getWitnesses()[$i]) ? $tx->getWitness($i) : null;
            $t['scripts'][] = $output->getScript()->getHex()    ;
        }

        $this->results[] = $t;

        return $this;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function result()
    {
        if ($this->knownResult !== null) {
            return $this->knownResult;
        }

        if (0 === count($this->results)) {
            return true;
        }

        $msg = [
            'id' => spl_object_hash($this),
            'flags' => 0,
            'txs' => $this->results
        ];
        $context = new \ZMQContext();
        $push = $context->getSocket(\ZMQ::SOCKET_REQ);
        $push->connect('tcp://127.0.0.1:6661');
        $push->send(json_encode($msg));
        $response = $push->recv();
        $result = json_decode($response, true);
        if (!isset($result['result']) || (bool) $result['result'] === false) {
            return false;
        }

        return true;
    }
}
