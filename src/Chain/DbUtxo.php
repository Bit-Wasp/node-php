<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutputInterface;
use BitWasp\Bitcoin\Utxo\Utxo;

class DbUtxo extends Utxo
{
    /**
     * @var int
     */
    private $id;

    /**
     * DbUtxo constructor.
     * @param int $id
     * @param OutPointInterface $outPoint
     * @param TransactionOutputInterface $prevOut
     */
    public function __construct($id, OutPointInterface $outPoint, TransactionOutputInterface $prevOut)
    {
        $this->id = $id;
        parent::__construct($outPoint, $prevOut);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}