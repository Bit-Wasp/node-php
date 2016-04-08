<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutputInterface;
use BitWasp\Bitcoin\Utxo\Utxo;

class ChainUtxo extends Utxo
{
    /**
     * @var int
     */
    private $height;

    /**
     * ChainUtxo constructor.
     * @param int $height
     * @param OutPointInterface $outPoint
     * @param TransactionOutputInterface $prevOut
     */
    public function __construct($height, OutPointInterface $outPoint, TransactionOutputInterface $prevOut)
    {
        parent::__construct($outPoint, $prevOut);
        $this->height = $height;
    }


    public function getHeight()
    {
        return $this->height;
    }
}