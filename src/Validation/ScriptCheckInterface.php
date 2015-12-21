<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Transaction\TransactionInterface;

interface ScriptCheckInterface
{
    /**
     * @param UtxoView $view
     * @param TransactionInterface $tx
     * @param Flags $flags
     * @param ScriptValidationState $scriptCheckState
     */
    public function check(UtxoView $view, TransactionInterface $tx, Flags $flags, ScriptValidationState $scriptCheckState);
}
