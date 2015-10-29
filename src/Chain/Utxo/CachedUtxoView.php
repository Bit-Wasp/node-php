<?php

namespace BitWasp\Bitcoin\Node\Chain\Utxo;


use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Transaction\TransactionInputInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Utxo\Utxo;

class CachedUtxoView implements UtxoViewInterface
{
    /**
     * @var UtxoCache
     */
    private $cache;

    /**
     * @param Utxo[] $utxos
     * @param UtxoCache $cache
     */
    public function __construct(array $utxos, UtxoCache $cache)
    {
        foreach ($utxos as $last => $utxo) {
            $cache->cache($utxo);
        }

        echo "Imported ".count($utxos)." to cache\n";
        $this->cache = $cache;
    }

    /**
     * @param $txid
     * @param $vout
     * @return bool
     */
    public function have($txid, $vout)
    {
        return $this->cache->have($txid, $vout);
    }

    /**
     * @param $txid
     * @param $vout
     * @return Utxo
     */
    public function fetch($txid, $vout)
    {
        return $this->cache->fetch($txid, $vout);
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