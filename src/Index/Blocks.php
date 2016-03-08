<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainsInterface;
use BitWasp\Bitcoin\Node\Chain\Forks;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Node\Index\Validation\BlockCheck;
use BitWasp\Bitcoin\Node\Index\Validation\BlockCheckInterface;
use BitWasp\Bitcoin\Node\Index\Validation\ScriptValidation;
use BitWasp\Bitcoin\Script\Interpreter\InterpreterInterface;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;

class Blocks
{
    /**
     * @var BlockCheckInterface
     */
    private $blockCheck;

    /**
     * @var ChainsInterface
     */
    private $chains;

    /**
     * @var Consensus
     */
    private $consensus;

    /**
     * @var DbInterface
     */
    private $db;

    /**
     * @var \BitWasp\Bitcoin\Math\Math
     */
    private $math;

    /**
     * Blocks constructor.
     * @param DbInterface $db
     * @param EcAdapterInterface $ecAdapter
     * @param ChainsInterface $chains
     * @param Consensus $consensus
     */
    public function __construct(
        DbInterface $db,
        EcAdapterInterface $ecAdapter,
        ChainsInterface $chains,
        Consensus $consensus
    ) {
    
        $this->db = $db;
        $this->math = $ecAdapter->getMath();
        $this->chains = $chains;
        $this->consensus = $consensus;
        $this->blockCheck = new BlockCheck($consensus, $ecAdapter);
    }

    /**
     * @param BlockInterface $genesisBlock
     */
    public function init(BlockInterface $genesisBlock)
    {
        $hash = $genesisBlock->getHeader()->getHash();
        $index = $this->db->fetchIndex($hash);

        try {
            $this->db->fetchBlock($hash);
        } catch (\Exception $e) {
            $this->db->createBlockIndexGenesis($index);
        }
    }

    /**
     * @param BufferInterface $hash
     * @return BlockInterface
     */
    public function fetch(BufferInterface $hash)
    {
        return $this->db->fetchBlock($hash);
    }

    /**
     * @param BlockInterface $block
     * @return array
     */
    public function parseUtxos(BlockInterface $block)
    {
        $unknown = [];
        $utxos = [];

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
                }
                $utxos[] = new Utxo(new OutPoint($hash, $i), $out);
            }
        }

        // Restore our list of unknown outpoints
        $required = [];
        foreach ($unknown as $str => $txidx) {
            $required[] = new OutPoint(new Buffer(substr($str, 0, 32), 32, $this->math), substr($str, 32));
        }

        return [$required, $utxos];
    }

    /**
     * @param BlockInterface $block
     * @return UtxoView
     */
    public function prepareBatch(BlockInterface $block)
    {
        list ($required, $utxos) = $this->parseUtxos($block);
        $remaining = $this->db->fetchUtxoList($block->getHeader()->getPrevBlock(), $required);
        foreach ($remaining as $utxo) {
            $utxos[] = $utxo;
        }
        return new UtxoView($utxos);
    }

    /**
     * @param BlockInterface $block
     * @param Headers $headers
     * @return BlockIndexInterface
     */
    public function accept(BlockInterface $block, Headers $headers)
    {
        $state = $this->chains->best();

        $hash = $block->getHeader()->getHash();
        $index = $headers->accept($hash, $block->getHeader());

        $this
            ->blockCheck
            ->check($block)
            ->checkContextual($block, $state->getLastBlock());

        $view = $this->prepareBatch($block);

        $versionInfo = $this->db->findSuperMajorityInfoByHash($block->getHeader()->getPrevBlock());
        $forks = new Forks($this->consensus->getParams(), $state->getLastBlock(), $versionInfo);
        $flags = $forks->getFlags();
        $scriptCheckState = new ScriptValidation(true, $flags);

        $nFees = 0;
        $nSigOps = 0;

        foreach ($block->getTransactions() as $tx) {
            $nSigOps += $this->blockCheck->getLegacySigOps($tx);

            if ($nSigOps > $this->consensus->getParams()->getMaxBlockSigOps()) {
                throw new \RuntimeException('Blocks::accept() - too many sigops');
            }

            if (!$tx->isCoinbase()) {
                if ($flags & InterpreterInterface::VERIFY_P2SH) {
                    $nSigOps = $this->blockCheck->getP2shSigOps($view, $tx);
                    if ($nSigOps > $this->consensus->getParams()->getMaxBlockSigOps()) {
                        throw new \RuntimeException('Blocks::accept() - too many sigops');
                    }
                }

                $fee = $this->math->sub($view->getValueIn($this->math, $tx), $tx->getValueOut());
                $nFees = $this->math->add($nFees, $fee);

                $this->blockCheck->checkInputs($view, $tx, $index->getHeight(), $flags, $scriptCheckState);
            }
        }

        if ($scriptCheckState->active() && !$scriptCheckState->result()) {
            throw new \RuntimeException('ScriptValidation failed!');
        }

        $this->blockCheck->checkCoinbaseSubsidy($block->getTransaction(0), $nFees, $index->getHeight());
        $state->updateLastBlock($index);

        $this->db->insertBlock($hash, $block);

        return $index;
    }
}
