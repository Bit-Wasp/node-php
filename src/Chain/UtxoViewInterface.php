<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Transaction\TransactionInputInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\BufferInterface;

interface UtxoViewInterface
{
    /**
     * @param BufferInterface $txid
     * @param int $vout
     * @return bool
     */
    public function have($txid, $vout);

    /**
     * @param BufferInterface $txid
     * @param int $vout
     * @return Utxo
     */
    public function fetch($txid, $vout);

    /**
     * @param TransactionInputInterface $input
     * @return Utxo
     */
    public function fetchByInput(TransactionInputInterface $input);

    /**
     * @param Math $math
     * @param TransactionInterface $transaction
     * @return int
     */
    public function getValueIn(Math $math, TransactionInterface $transaction);
}
