<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Buffertools\Buffer;
use Evenement\EventEmitterInterface;

/**
 * This class retains all of this in memory. It must be
 * rebuilt on startup.
 */
interface ChainInterface extends EventEmitterInterface
{
    /**
     * @param Buffer $hash
     * @return bool
     */
    public function containsHash(Buffer $hash);

    /**
     * @param Buffer $hash
     * @return BlockIndexInterface
     */
    public function fetchIndex(Buffer $hash);

    /**
     * @param int $height
     * @return BlockIndexInterface
     */
    public function fetchAncestor($height);

    /**
     * @return BlockIndexInterface
     */
    public function getIndex();

    /**
     * @return ChainCacheInterface
     */
    public function getChainCache();

    /**
     * @param Buffer $hash
     * @return int
     */
    public function getHeightFromHash(Buffer $hash);

    /**
     * @param int $height
     * @return Buffer
     */
    public function getHashFromHeight($height);

    /**
     * @param BlockIndexInterface $index
     */
    public function updateTip(BlockIndexInterface $index);
}
