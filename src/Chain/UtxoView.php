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
    private $v = [];
    /**
     * @param Utxo[] $utxos
     */
    public function __construct(array $utxos)
    {
        foreach ($utxos as $utxoKey => $output) {
            $this->addUtxo($utxoKey, $output);
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
    private function addUtxo($key, Utxo $utxo)
    {
        $this->utxo[$key] = $utxo;
    }

    /**
     * @param OutPointInterface $outpoint
     * @return bool
     */
    public function have(OutPointInterface $outpoint)
    {
        return array_key_exists($this->makeKey($outpoint), $this->utxo);
    }

    /**
     * @param OutPointInterface $outpoint
     * @return string
     */
    protected function makeKey(OutPointInterface $outpoint)
    {
        $n = $outpoint->getVout();
        if (array_key_exists($n, $this->v)) {
            $s = $this->v[$n];
        } else {
            $s = $this->v[$n] = pack("V", $n);
        }

        return "{$outpoint->getTxId()->getBinary()}{$s}";
    }

    /**
     * @param OutPointInterface $outpoint
     * @return Utxo
     */
    public function fetch(OutPointInterface $outpoint)
    {
        $key = $this->makeKey($outpoint);
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
     * @return \GMP
     */
    public function getValueIn(Math $math, TransactionInterface $tx)
    {
        $value = gmp_init(0);
        foreach ($tx->getInputs() as $input) {
            $value = $math->add($value, gmp_init($this->fetchByInput($input)->getOutput()->getValue()));
        }

        return $value;
    }

    /**
     * @param Math $math
     * @param TransactionInterface $tx
     * @return \GMP
     */
    public function getFeePaid(Math $math, TransactionInterface $tx)
    {
        return $math->sub($this->getValueIn($math, $tx), gmp_init($tx->getValueOut()));
    }
}
