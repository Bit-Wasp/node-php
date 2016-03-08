<?php

namespace BitWasp\Bitcoin\Node\Chain\Utxo;

use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Transaction\TransactionInputInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Utxo\Utxo;
use Doctrine\Common\Cache\Cache;

class UtxoCache implements UtxoViewInterface
{
    /**
     * @var Cache
     */
    private $cache;

    /**
     * @param Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param string $txid
     * @param int $vout
     * @return string
     */
    private function getInternalIndex($txid, $vout)
    {
        return "{$txid}_{$vout}";
    }

    /**
     * @param string $txid
     * @param int $vout
     * @return bool
     */
    public function have($txid, $vout)
    {
        $index = $this->getInternalIndex($txid, $vout);
        return $this->cache->contains($index);
    }

    /**
     * @param string $txid
     * @param int $vout
     * @return Utxo
     */
    public function fetch($txid, $vout)
    {
        $index = $this->getInternalIndex($txid, $vout);
        if (!$this->cache->contains($index)) {
            throw new \RuntimeException('Utxo not found in this cache');
        }

        return $this->cache->fetch($index);
    }

    /**
     * @param TransactionInputInterface $input
     * @return Utxo
     */
    public function fetchByInput(TransactionInputInterface $input)
    {
        return $this->fetch($input->getOutPoint()->getTxId(), $input->getOutPoint()->getVout());
    }

    /**
     * @param Math $math
     * @param TransactionInterface $transaction
     * @return int|string
     */
    public function getValueIn(Math $math, TransactionInterface $transaction)
    {
        $value = 0;

        foreach ($transaction->getInputs() as $input) {
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

    /**
     * @param Utxo $utxo
     */
    public function cache(Utxo $utxo)
    {
        $index = $this->getInternalIndex($utxo->getOutPoint()->getTxId(), $utxo->getOutPoint()->getVout());
        $this->cache->save($index, $utxo, 5 * 60);
    }

    /**
     * @param string $txid
     * @param int $vout
     */
    public function remove($txid, $vout)
    {
        $this->cache->delete($this->getInternalIndex($txid, $vout));
    }
}
