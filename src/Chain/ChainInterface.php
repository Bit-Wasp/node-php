<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Node\Index\Transactions;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitterInterface;

/**
 * This class retains all of this in memory. It must be
 * rebuilt on startup.
 */
interface ChainInterface extends EventEmitterInterface
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
}
