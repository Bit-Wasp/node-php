<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\TransactionInterface;

class ScriptValidation implements ScriptValidationInterface
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
     * ScriptValidationState constructor.
     * @param bool $active
     */
    public function __construct($active = true)
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
     * @param Flags $flags
     * @return ScriptValidationInterface
     */
    public function queue(UtxoView $utxoView, TransactionInterface $tx, Flags $flags)
    {
        for ($i = 0, $c = count($tx->getInputs()); $i < $c; $i++) {
            $scriptPubKey = $utxoView
                ->fetchByInput($tx->getInput($i))
                ->getOutput()
                ->getScript();

            $this->results[] = ScriptFactory::consensus($flags)->verify($tx, $scriptPubKey, $i);
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

        $result = true;
        foreach ($this->results as $r) {
            if ($result === true) {
                $result = $result && $r;
            }
        }

        $this->knownResult = $result;
        $this->results = [];

        return $result;
    }
}
