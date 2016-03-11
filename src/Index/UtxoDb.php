<?php

namespace BitWasp\Bitcoin\Node\Index;


use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\Buffer;

class UtxoDb
{
    /**
     * @var Math
     */
    private $math;

    /**
     * @var DbInterface
     */
    private $db;

    /**
     * UtxoDb constructor.
     * @param DbInterface $db
     * @param Math $math
     */
    public function __construct(DbInterface $db, Math $math)
    {
        $this->math = $math;
        $this->db = $db;
    }

    /**
     * @param BlockInterface $block
     * @return array
     */
    public function parseUtxos(BlockInterface $block)
    {
        $unknown = [];
        $newOutputs = [];

        // Record every Outpoint required for the block.
        foreach ($block->getTransactions() as $t => $tx) {
            if ($tx->isCoinbase()) {
                continue;
            }

            foreach ($tx->getInputs() as $in) {
                $outpoint = $in->getOutPoint();
                $unknown[$outpoint->getTxId()->getBinary() . $outpoint->getVout()] = $t;
            }
        }

        // Cancel outpoints which were used in a subsequent transaction
        foreach ($block->getTransactions() as $tx) {
            $hash = $tx->getTxId();
            $hashBin = $hash->getBinary();
            foreach ($tx->getOutputs() as $i => $out) {
                $lookup = $hashBin . $i;
                if (isset($unknown[$lookup])) {
                    unset($unknown[$lookup]);
                } else {
                    $newOutputs[] = new Utxo(new OutPoint($hash, $i), $out);
                }
            }
        }

        // Restore our list of unknown outpoints
        $spent = [];
        foreach ($unknown as $str => $txidx) {
            $spent[] = new OutPoint(new Buffer(substr($str, 0, 32), 32, $this->math), substr($str, 32));
        }

        return [$spent, $newOutputs];
    }

    public function update(ChainStateInterface $chainState, BlockInterface $block)
    {
        list ($spent, $newOutputs) = $this->parseUtxos($block);
        $this->db->updateUtxoSet($spent, $newOutputs);
    }

}