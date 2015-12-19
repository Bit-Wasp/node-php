<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\Chains;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\Db;
use BitWasp\Bitcoin\Node\Validation\BlockCheckInterface;
use BitWasp\Bitcoin\Script\Interpreter\InterpreterInterface;
use BitWasp\Buffertools\Buffer;

class Blocks
{
    /**
     * @var BlockCheckInterface
     */
    private $blockCheck;

    /**
     * @var Chains
     */
    private $chains;

    /**
     * @var Consensus
     */
    private $consensus;

    /**
     * @var Db
     */
    private $db;

    /**
     * @var \BitWasp\Bitcoin\Math\Math
     */
    private $math;

    /**
     * @param Db $db
     * @param EcAdapterInterface $ecAdapter
     * @param Consensus $consensus
     * @param BlockCheckInterface $blockCheck
     */
    public function __construct(
        Db $db,
        EcAdapterInterface $ecAdapter,
        Chains $chains,
        Consensus $consensus,
        BlockCheckInterface $blockCheck
    ) {
        $this->chains = $chains;
        $this->blockCheck = $blockCheck;
        $this->consensus = $consensus;
        $this->db = $db;
        $this->math = $ecAdapter->getMath();
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
     * @param Buffer $hash
     * @return BlockInterface
     */
    public function fetch(Buffer $hash)
    {
        return $this->db->fetchBlock($hash);
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

        $view = $this->db->fetchUtxoView($block);

        //$forks = new ForkState($index, $this->consensus->getParams(), $this->db);
        //$flags = $forks->getScriptFlags();
        $flags = new \BitWasp\Bitcoin\Flags($this->math->cmp($index->getHeader()->getTimestamp(), $this->consensus->getParams()->p2shActivateTime()) >= 0 ? InterpreterInterface::VERIFY_P2SH : InterpreterInterface::VERIFY_NONE);
        $nInputs = 0;
        $nFees = 0;
        $nSigOps = 0;

        foreach ($block->getTransactions() as $tx) {
            $nInputs += count($tx->getInputs());
            $nSigOps += $this->blockCheck->getLegacySigOps($tx);

            if ($nSigOps > $this->consensus->getParams()->getMaxBlockSigOps()) {
                throw new \RuntimeException('Blocks::accept() - too many sigops');
            }

            if (!$tx->isCoinbase()) {
                if ($flags->checkFlags(InterpreterInterface::VERIFY_P2SH)) {
                    $nSigOps = $this->blockCheck->getP2shSigOps($view, $tx);
                    if ($nSigOps > $this->consensus->getParams()->getMaxBlockSigOps()) {
                        throw new \RuntimeException('Blocks::accept() - too many sigops');
                    }
                }

                $fee = $this->math->sub($view->getValueIn($this->math, $tx), $tx->getValueOut());
                $nFees = $this->math->add($nFees, $fee);

                $this->blockCheck->checkInputs($view, $tx, $index->getHeight(), $flags);
            }
        }

        $this->blockCheck->checkCoinbaseSubsidy($block->getTransaction(0), $nFees, $index->getHeight());
        $state->updateLastBlock($index);
        $this->db->insertBlock($hash, $block);

        return $index;
    }
}
