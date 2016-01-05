<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Flags;
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
     * @param Flags $flags
     * @return self
     */
    public function queue(UtxoView $utxoView, TransactionInterface $tx, Flags $flags);

    /**
     * @return bool
     * @throws \Exception
     */
    public function result();
}
