<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Buffertools\BufferInterface;

interface ChainStateInterface
{
    /**
     * @param BlockIndexInterface $blockIndex
     */
    public function updateTip(BlockIndexInterface $blockIndex);

    /**
     * @param BlockIndexInterface $index
     */
    public function updateLastBlock(BlockIndexInterface $index);

    /**
     * @return ChainInterface
     */
    public function getChain();

    /**
     * @return BlockIndexInterface
     */
    public function getChainIndex();

    /**
     * @return BlockIndexInterface
     */
    public function getLastBlock();

    /**
     * @return ChainCacheInterface
     */
    public function bestBlocksCache();

    /**
     * @return int|string
     */
    public function blocksLeftToSync();

    /**
     * @return bool
     */
    public function isSyncing();

    /**
     * Produce a block locator for a given block height.
     * @param int $height
     * @param BufferInterface|null $final
     * @return BlockLocator
     */
    public function getLocator($height, BufferInterface $final = null);

    /**
     * @param BufferInterface|null $hashStop
     * @return BlockLocator
     */
    public function getHeadersLocator(BufferInterface $hashStop = null);

    /**
     * @param BufferInterface|null $hashStop
     * @return BlockLocator
     */
    public function getBlockLocator(BufferInterface $hashStop = null);
}
