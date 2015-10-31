<?php

namespace BitWasp\Bitcoin\Node\Chain\Utxo;


use BitWasp\Bitcoin\Math\Math;
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
        if (!isset($this->utxo[$utxo->getTransactionId()])) {
            $this->utxo[$utxo->getTransactionId()] = [$utxo->getVout() => $utxo];
        } else {
            $this->utxo[$utxo->getTransactionId()][$utxo->getVout()] = $utxo;
        }
    }

    /**
     * @param string $txid
     * @param int $vout
     * @return bool
     */
    public function have($txid, $vout)
    {
        if (isset($this->utxo[$txid])) {
            if (isset($this->utxo[$txid][$vout])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $txid
     * @param int $vout
     * @return Utxo
     */
    public function fetch($txid, $vout)
    {
        if (!$this->have($txid, $vout)) {
            echo "[$txid, $vout]\n";
            throw new \RuntimeException('Utxo not found in this UtxoView');
        }

        return $this->utxo[$txid][$vout];
    }

    /**
     * @param TransactionInputInterface $input
     * @return Utxo
     */
    public function fetchByInput(TransactionInputInterface $input)
    {
        return $this->fetch($input->getTransactionId(), $input->getVout());
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
            $value = $math->add(
                $value,
                $this
                    ->fetchByInput($input)
                    ->getOutput()
                    ->getValue()
            );
        }

        return $value;
    }
}