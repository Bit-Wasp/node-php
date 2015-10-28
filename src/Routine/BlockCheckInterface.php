<?php

namespace BitWasp\Bitcoin\Node\Routine;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\UtxoView;
use BitWasp\Bitcoin\Transaction\TransactionInterface;

interface BlockCheckInterface
{
    /**
     * @param TransactionInterface $tx
     * @return int
     */
    public function getLegacySigOps(TransactionInterface $tx);

    /**
     * @param UtxoView $view
     * @param TransactionInterface $tx
     * @return int
     */
    public function getP2shSigOps(UtxoView $view, TransactionInterface $tx);

    /**
     * @param TransactionInterface $coinbase
     * @param int $nFees
     * @param int $height
     * @return $this
     */
    public function checkCoinbaseSubsidy(TransactionInterface $coinbase, $nFees, $height);

    /**
     * @param TransactionInterface $tx
     * @param int $height
     * @param int $time
     * @return bool|int
     */
    public function checkTransactionIsFinal(TransactionInterface $tx, $height, $time);

    /**
     * @param TransactionInterface $transaction
     * @param bool|true $checkSize
     * @return bool
     */
    public function checkTransaction(TransactionInterface $transaction, $checkSize = true);

    /**
     * @param BlockInterface $block
     * @return $this
     */
    public function check(BlockInterface $block);

    /**
     * @param UtxoView $view
     * @param TransactionInterface $tx
     * @param $spendHeight
     * @return bool
     */
    public function checkContextualInputs(UtxoView $view, TransactionInterface $tx, $spendHeight);

    /**
     * @param BlockInterface $block
     * @param BlockIndex $prevBlockIndex
     * @return bool
     */
    public function checkContextual(BlockInterface $block, BlockIndex $prevBlockIndex);

    /**
     * @param UtxoView $view
     * @param TransactionInterface $tx
     * @param int $height
     * @param Flags $flags
     * @param bool|true $checkScripts
     * @return bool
     */
    public function checkInputs(UtxoView $view, TransactionInterface $tx, $height, Flags $flags, $checkScripts = true);
}