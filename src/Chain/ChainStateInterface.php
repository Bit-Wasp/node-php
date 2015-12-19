<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Buffertools\Buffer;

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
     * Produce a block locator for a given block height.
     * @param int $height
     * @param Buffer|null $final
     * @return BlockLocator
     */
    public function getLocator($height, Buffer $final = null);

    /**
     * @param Buffer|null $hashStop
     * @return BlockLocator
     */
    public function getHeadersLocator(Buffer $hashStop = null);

    /**
     * @param Buffer|null $hashStop
     * @return BlockLocator
     */
    public function getBlockLocator(Buffer $hashStop = null);
}
