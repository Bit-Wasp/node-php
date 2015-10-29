<?php

namespace BitWasp\Bitcoin\Node\Index;


use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoViewInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain\Chains;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\Chain\Utxo\CachedUtxoView;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoCache;
use BitWasp\Bitcoin\Node\Chain\UtxoView;
use BitWasp\Bitcoin\Node\Db;
use BitWasp\Bitcoin\Utxo\Utxo;
use Doctrine\Common\Cache\ArrayCache;

class UtxoIdx
{
    private $db;
    private $sets;

    public function __construct(Chains $chains, Db $db)
    {
        $this->db = $db;
        $this->sets = new \SplObjectStorage();
        foreach ($chains as $chain) {
            $this->sets->attach($chain, new CachedUtxoView([], new UtxoCache(new ArrayCache())));
        }
    }

    /**
     * @param BlockInterface $block
     * @return array
     */
    public function reduceFromBlock( BlockInterface $block, $utxos = [])
    {
        $need = [];

        // Iterating backwards, record all required inputs.
        // If an Output can be found in a transaction in
        // the same block, it will be dropped from the list
        // of required inputs, and returned as a UTXO.

        $vTx = $block->getTransactions();
        for ($i = count($vTx) - 1; $i > 0; $i--) {
            $tx = $vTx->get($i);
            foreach ($tx->getInputs() as $in) {
                $txid = $in->getTransactionId();
                $vout = $in->getVout();
                $need[$txid.$vout] = $i;
            }

            $hash = $tx->getTxId()->getHex();
            foreach ($tx->getOutputs() as $v => $out) {
                if (isset($need[$hash.$v])) {
                    $utxos[] = new Utxo($hash, $v, $out);
                    unset($need[$hash.$v]);
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
     * @param ChainState $state
     * @param $required
     * @param Utxo[] $utxos
     * @return array
     */
    public function reduceFromCache(ChainState $state, $required, $utxos = [])
    {
        /** @var UtxoViewInterface $cache */
        $cache = $this->sets->offsetGet($state);
        $stillRequired = [];
        foreach ($required as $input) {
            list ($txid, $vout) = $input;
            try {
                $utxo = $cache->fetch($txid, $vout);
                $utxos[] = $utxo;
            } catch (\Exception $e) {
                $stillRequired[] = $input;
            }
        }

        return [$stillRequired, $utxos];
    }

    /**
     * @param BlockInterface $block
     * @return UtxoView
     */
    public function fetchView(ChainState $state, BlockInterface $block)
    {
        $txs = $block->getTransactions();
        $txCount = count($txs);
        if (1 == $txCount) {
            return new UtxoView([]);
        }

        list ($required, $outputSet) = $this->reduceFromBlock($block);
        list ($required, $outputSet) = $this->reduceFromCache($state, $required, $outputSet);

        $view = $this->db->fetchUtxos($required, $block->getHeader()->getPrevBlock());
        return $view;
    }
    public function createView()
    {

    }
}