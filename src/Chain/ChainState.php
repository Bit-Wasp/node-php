<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;

class ChainState implements ChainStateInterface
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
     * @var GuidedChainCache
     */
    private $lastBlockCache;

    /**
     * ChainState constructor.
     * @param ChainInterface $chain
     * @param BlockIndexInterface $lastBlock
     */
    public function __construct(ChainInterface $chain, BlockIndexInterface $lastBlock)
    {
        $this->chain = $chain;
        $this->lastBlockCache = new GuidedChainCache($this->chain->getChainCache(), $lastBlock->getHeight());
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
        $this->lastBlockCache->add($index);
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
        return $this->lastBlockCache;
    }

    /**
     * @return int|string
     */
    public function blocksLeftToSync()
    {
        return ($this->chain->getIndex()->getHeight() - $this->lastBlock->getHeight());
    }

    /**
     * @return bool
     */
    public function isSyncing()
    {
        return (($this->blocksLeftToSync() === 0) === false);
    }

    /**
     * Produce a block locator for a given block height.
     * @param int $height
     * @param BufferInterface|null $final
     * @return BlockLocator
     */
    public function getLocator($height, BufferInterface $final = null)
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
     * @param BufferInterface|null $hashStop
     * @return BlockLocator
     */
    public function getHeadersLocator(BufferInterface $hashStop = null)
    {
        return $this->getLocator($this->chain->getIndex()->getHeight(), $hashStop);
    }

    /**
     * @param BufferInterface|null $hashStop
     * @return BlockLocator
     */
    public function getBlockLocator(BufferInterface $hashStop = null)
    {
        return $this->getLocator($this->lastBlock->getHeight(), $hashStop);
    }
}
