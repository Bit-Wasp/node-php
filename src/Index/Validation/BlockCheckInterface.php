<?php

namespace BitWasp\Bitcoin\Node\Index\Validation;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Node\Serializer\Transaction\CachingTransactionSerializer;
use BitWasp\Bitcoin\Serializer\Block\BlockSerializerInterface;
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
     * @param CachingTransactionSerializer $txSerializer
     * @param bool|true $checkSize
     * @return $this
     */
    public function checkTransaction(TransactionInterface $transaction, CachingTransactionSerializer $txSerializer, $checkSize = true);

    /**
     * @param BlockInterface $block
     * @param CachingTransactionSerializer $txSerializer
     * @param BlockSerializerInterface $blockSerializer
     * @return mixed
     */
    public function check(BlockInterface $block, CachingTransactionSerializer $txSerializer, BlockSerializerInterface $blockSerializer);

    /**
     * @param UtxoView $view
     * @param TransactionInterface $tx
     * @param $spendHeight
     * @return $this
     */
    public function checkContextualInputs(UtxoView $view, TransactionInterface $tx, $spendHeight);

    /**
     * @param BlockInterface $block
     * @param BlockIndexInterface $prevBlockIndex
     * @return $this
     */
    public function checkContextual(BlockInterface $block, BlockIndexInterface $prevBlockIndex);

    /**
     * @param UtxoView $view
     * @param TransactionInterface $tx
     * @param int $height
     * @param int $flags
     * @param ScriptValidationInterface $state
     * @return $this
     */
    public function checkInputs(UtxoView $view, TransactionInterface $tx, $height, $flags, ScriptValidationInterface $state);
}
