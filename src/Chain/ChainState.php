<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Buffertools\Buffer;

class ChainState
{
    /**
     * @var ChainInterface
     */
    private $chain;

    /**
     * @var BlockIndexInterface
     */
    private $lastBlock;

    /**
     * ChainState constructor.
     * @param ChainInterface $chain
     * @param BlockIndexInterface $lastBlock
     */
    public function __construct(ChainInterface $chain, BlockIndexInterface $lastBlock)
    {
        $this->chain = $chain;
        $this->lastBlock = $lastBlock;
    }

    /**
     * @param BlockIndexInterface $blockIndex
     */
    public function updateTip(BlockIndexInterface $blockIndex)
    {
        $this->chain->updateTip($blockIndex);
    }

    /**
     * @param BlockIndexInterface $index
     */
    public function updateLastBlock(BlockIndexInterface $index)
    {
        if ($this->lastBlock->getHash() != $index->getHeader()->getPrevBlock()) {
            throw new \RuntimeException('UpdateLastBlock: Block does not extend this chain');
        }

        if ($this->lastBlock->getHeight() != ($index->getHeight() - 1)) {
            throw new \RuntimeException('UpdateLastBlock: Incorrect chain height' . ($index->getHeight() - 1));
        }

        $this->lastBlock = $index;
    }

    /**
     * @return ChainInterface
     */
    public function getChain()
    {
        return $this->chain;
    }

    /**
     * @return BlockIndexInterface
     */
    public function getChainIndex()
    {
        return $this->chain->getIndex();
    }

    /**
     * @return BlockIndexInterface
     */
    public function getLastBlock()
    {
        return $this->lastBlock;
    }

    /**
     * @return ChainCacheInterface
     */
    public function bestBlocksCache()
    {
        return $this->chain
            ->getChainCache()
            ->subset($this->lastBlock->getHeight());
    }

    /**
     * @return int|string
     */
    public function blocksLeftToSync()
    {
        return ($this->chain->getIndex()->getHeight() - $this->lastBlock->getHeight());
    }

    /**
     * Produce a block locator for a given block height.
     * @param int $height
     * @param Buffer|null $final
     * @return BlockLocator
     */
    public function getLocator($height, Buffer $final = null)
    {
        $step = 1;
        $hashes = [];
        $headerHash = $this->chain->getHashFromHeight($height);

        while (true) {
            $hashes[] = $headerHash;
            if ($height === 0) {
                break;
            }

            $height = max($height - $step, 0);
            $headerHash = $this->chain->getHashFromHeight($height);
            if (count($hashes) >= 10) {
                $step *= 2;
            }
        }

        if (null === $final) {
            $hashStop = new Buffer('', 32);
        } else {
            $hashStop = $final;
        }

        return new BlockLocator(
            $hashes,
            $hashStop
        );
    }

    /**
     * @param Buffer|null $hashStop
     * @return BlockLocator
     */
    public function getHeadersLocator(Buffer $hashStop = null)
    {
        return $this->getLocator($this->chain->getIndex()->getHeight(), $hashStop);
    }

    /**
     * @param Buffer|null $hashStop
     * @return BlockLocator
     */
    public function getBlockLocator(Buffer $hashStop = null)
    {
        return $this->getLocator($this->lastBlock->getHeight(), $hashStop);
    }
}
