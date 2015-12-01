<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain\Chains;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoCache;
use BitWasp\Bitcoin\Node\Db;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Utxo\Utxo;

class UtxoIdx
{
    /**
     * @var Db
     */
    private $db;

    /**
     * UtxoIdx constructor.
     * @param Chains $chains
     * @param Db $db
     */
    public function __construct(Chains $chains, Db $db)
    {
        $this->db = $db;
    }

    /**
     * @param BlockInterface $block
     * @return array
     */
    public function filter(BlockInterface $block)
    {
        $need = [];

        // Iterating backwards, record all required inputs.
        // If an Output can be found in a transaction in
        // the same block, it will be dropped from the list
        // of required inputs.
        // Any UTXO's from the block which are not consumed
        // are returned as the second value.

        $utxos = [];
        $vTx = $block->getTransactions();
        for ($i = count($vTx) - 1; $i > 0; $i--) {
            $tx = $vTx[$i];
            foreach ($tx->getInputs() as $in) {
                $index = $in->getTransactionId() . $in->getVout();
                $need[$index] = $i;
            }

            $hash = $tx->getTxId()->getHex();
            foreach ($tx->getOutputs() as $v => $out) {
                $index = $hash . $v;
                if (isset($need[$index])) {
                    unset($need[$index]);
                } else {
                    $utxos[] = new Utxo($hash, $v, $out);
                }
            }
        }

        $required = [];
        foreach ($need as $str => $txidx) {
            $required[] = [substr($str, 0, 64), substr($str, 64), $txidx];
        }

        return [$required, $utxos];
    }

    /**
     * @param UtxoCache $cache
     * @param array $required
     * @return array
     */
    public function reduceCache(UtxoCache $cache, array $required)
    {
        $utxos = [];
        $stillRequired = [];
        foreach ($required as $input) {
            list ($txid, $vout) = $input;
            try {
                echo "found in cache!\n";
                $utxos[] = $cache->fetch($txid, $vout);
                $cache->remove($txid, $vout);
            } catch (\Exception $e) {
                echo '.';
                $stillRequired[] = $input;
            }
        }

        return [$stillRequired, $utxos];
    }

    /**
     * @param ChainState $state
     * @param BlockInterface $block
     * @return UtxoView
     */
    public function fetchView(ChainState $state, BlockInterface $block)
    {
        $txs = $block->getTransactions();
        $txCount = count($txs);
        if (1 === $txCount) {
            return new UtxoView([]);
        }

        list ($required, $newUnspent) = $this->filter($block);

        $found = $this->db->fetchUtxos($required, $block->getHeader()->getPrevBlock());

        return new UtxoView(array_merge($found, $newUnspent));
    }
}
