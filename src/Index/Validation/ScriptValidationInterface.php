<?php

namespace BitWasp\Bitcoin\Node\Index\Validation;

use BitWasp\Bitcoin\Node\Chain\UtxoView;
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
     * @return self
     */
    public function queue(UtxoView $utxoView, TransactionInterface $tx);

    /**
     * @return bool
     * @throws \Exception
     */
    public function result();
}
