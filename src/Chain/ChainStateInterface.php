<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Node\Index\Transactions;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitterInterface;

interface ChainStateInterface extends EventEmitterInterface
{
    /**
     * @param BufferInterface $hash
     * @return bool
     */
    public function containsHash(BufferInterface $hash);

    /**
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetchIndex(BufferInterface $hash);

    /**
     * @param Transactions $txIndex
     * @param BufferInterface $txid
     * @return TransactionInterface
     */
    public function fetchTransaction(Transactions $txIndex, BufferInterface $txid);

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
     * @param BufferInterface $hash
     * @return int
     */
    public function getHeightFromHash(BufferInterface $hash);

    /**
     * @param int $height
     * @return BufferInterface
     */
    public function getHashFromHeight($height);

    /**
     * @param BlockIndexInterface $index
     */
    public function updateTip(BlockIndexInterface $index);

    /**
     * @param BlockIndexInterface $index
     */
    public function updateLastBlock(BlockIndexInterface $index);

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
