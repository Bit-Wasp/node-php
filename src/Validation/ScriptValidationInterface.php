<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Transaction\TransactionInterface;

interface ScriptValidationInterface
{
    /**
     * @return bool
     */
    public function active();

    /**
     * @param UtxoView $utxoView
     * @param TransactionInterface $tx
     * @param int $flags
     * @return self
     */
    public function queue(UtxoView $utxoView, TransactionInterface $tx, $flags);

    /**
     * @return bool
     * @throws \Exception
     */
    public function result();
}
