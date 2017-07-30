<?php

namespace BitWasp\Bitcoin\Node\Index\Validation;

use BitWasp\Bitcoin\Node\Chain\UtxoView;
use BitWasp\Bitcoin\Node\Consensus\BitcoinConsensus;
use BitWasp\Bitcoin\Script\Consensus\NativeConsensus;
use BitWasp\Bitcoin\Script\Interpreter\InterpreterInterface;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Serializer\Transaction\TransactionSerializerInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;

class ScriptValidation implements ScriptValidationInterface
{
    /**
     * @var bool
     */
    private $active;

    /**
     * @var int
     */
    private $flags;

    /**
     * @var \BitWasp\Bitcoin\Script\Consensus\ConsensusInterface
     */
    private $consensus;

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
    public function __construct($active = true, $flags = InterpreterInterface::VERIFY_NONE, TransactionSerializerInterface $txSerializer)
    {
        if (!is_bool($active)) {
            throw new \InvalidArgumentException('ScriptValidationState: $active should be bool');
        }

        $this->active = $active;
        $this->flags = $flags;

        if (!extension_loaded('bitcoinconsensus')) {
            $this->consensus = new NativeConsensus();
        } else {
            $this->consensus = new BitcoinConsensus($txSerializer);
        }
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
        for ($i = 0, $c = count($tx->getInputs()); $i < $c; $i++) {
            $output = $utxoView->fetchByInput($tx->getInput($i))->getOutput();
            $this->results[] = $this->consensus->verify($tx, $output->getScript(), $this->flags, $i, $output->getValue());
        }

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

        $result = count(array_filter($this->results, function ($value) {
            return !$value;
        })) === 0;

        $this->knownResult = $result;
        $this->results = [];

        return $result;
    }
}
