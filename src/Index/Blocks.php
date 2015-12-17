<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\ChainState;
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
        Consensus $consensus,
        BlockCheckInterface $blockCheck
    ) {
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
     * @param ChainState $state
     * @param BlockInterface $block
     * @param Headers $headers
     * @param UtxoIdx $utxoIdx
     * @return BlockIndex
     */
    public function accept(ChainState $state, BlockInterface $block, Headers $headers, UtxoIdx $utxoIdx)
    {
        echo "Blocks::ACCEPT START\n";
        $bestBlock = $state->getLastBlock();
        if ($bestBlock->getHash() != $block->getHeader()->getPrevBlock()) {
            throw new \RuntimeException('Blocks:accept() Block does not extend this chain!');
        }

        $index = $headers->accept($state, $block->getHeader());

        $m1 = microtime(true);
        $this
            ->blockCheck
            ->check($block)
            ->checkContextual($block, $bestBlock);
        echo "Validation took " . (microtime(true) - $m1) . " seconds\n";

        $view = $this->db->fetchUtxoView($block);

        $header = $block->getHeader();
        $flagP2sh =  $this->math->cmp($header->getTimestamp(), $this->consensus->getParams()->p2shActivateTime()) >= 0;

        $flags = new Flags($flagP2sh ? InterpreterInterface::VERIFY_P2SH : InterpreterInterface::VERIFY_NONE);

        if ($this->math->cmp($header->getVersion(), 3) >= 0) {

        }

        if ($this->math->cmp($header->getVersion(), 4) >= 0) {

        }

        $nInputs = 0;
        $nFees = 0;
        $nSigOps = 0;
        $txs = $block->getTransactions();
        $m1 = microtime(true);
        foreach ($block->getTransactions() as $tx) {
            $nInputs += count($tx->getInputs());

            $nSigOps += $this->blockCheck->getLegacySigOps($tx);
            if ($nSigOps > $this->consensus->getParams()->getMaxBlockSigOps()) {
                throw new \RuntimeException('Blocks::accept() - too many sigops');
            }

            if (!$tx->isCoinbase()) {
                if ($flagP2sh) {
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
        echo "Other checks " . (microtime(true) - $m1) . " - seconds \n";

        $this->blockCheck->checkCoinbaseSubsidy($txs[0], $nFees, $index->getHeight());

        $this->db->insertBlock($block);

        $state->updateLastBlock($index);
        echo "Blocks::ACCEPT END\n";
        return $index;
    }
}
