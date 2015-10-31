<?php

namespace BitWasp\Bitcoin\Node\Chain\Utxo;


use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Transaction\TransactionInputInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;

interface UtxoViewInterface
{
    public function have($txid, $vout);
    public function fetch($txid, $vout);
    public function fetchByInput(TransactionInputInterface $input);
    public function getValueIn(Math $math, TransactionInterface $transaction);
}