<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\TransactionInputInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Utxo\Utxo;

class UtxoView implements \Countable
{
    /**
     * @var array
     */
    private $utxo = [];

    /**
     * @param Utxo[] $utxos
     */
    public function __construct(array $utxos)
    {
        foreach ($utxos as $output) {
            $this->addUtxo($output);
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->utxo);
    }

    /**
     * @param Utxo $utxo
     */
    private function addUtxo(Utxo $utxo)
    {
        $this->utxo[$utxo->getOutPoint()->getTxId()->getBinary() . $utxo->getOutPoint()->getVout()] = $utxo;
    }

    /**
     * @param OutPointInterface $outpoint
     * @return bool
     */
    public function have(OutPointInterface $outpoint)
    {
        return array_key_exists($outpoint->getTxId()->getBinary() . $outpoint->getVout(), $this->utxo);
    }

    /**
     * @param OutPointInterface $outpoint
     * @return Utxo
     */
    public function fetch(OutPointInterface $outpoint)
    {
        $key = $outpoint->getTxId()->getBinary() . $outpoint->getVout();
        if (!isset($this->utxo[$key])) {
            throw new \RuntimeException('Utxo not found in this UtxoView');
        }

        return $this->utxo[$key];
    }

    /**
     * @param TransactionInputInterface $input
     * @return Utxo
     */
    public function fetchByInput(TransactionInputInterface $input)
    {
        return $this->fetch($input->getOutPoint());
    }

    /**
     * @param Math $math
     * @param TransactionInterface $tx
     * @return int|string
     */
    public function getValueIn(Math $math, TransactionInterface $tx)
    {
        $value = 0;
        foreach ($tx->getInputs() as $input) {
            $value = $math->add($value, $this->fetchByInput($input)->getOutput()->getValue());
        }

        return $value;
    }

    /**
     * @param Math $math
     * @param TransactionInterface $tx
     * @return int|string
     */
    public function getFeePaid(Math $math, TransactionInterface $tx)
    {
        $valueIn = $this->getValueIn($math, $tx);
        $valueOut = $tx->getValueOut();

        return $math->sub($valueIn, $valueOut);
    }
}
