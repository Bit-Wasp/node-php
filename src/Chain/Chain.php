<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Index;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitter;

/**
 * This class retains all of this in memory. It must be
 * rebuilt on startup.
 */
class Chain extends EventEmitter implements ChainInterface
{
    /**
     * @var Index\Headers
     */
    private $headers;

    /**
     * @var Index\Transaction
     */
    private $txIndex;

    /**
     * @var BlockIndexInterface
     */
    private $index;

    /**
     * @var ChainCacheInterface
     */
    private $chainCache;

    /**
     * @var Math
     */
    private $math;

    /**
     * Chain constructor.
     * @param string[] $map
     * @param BlockIndexInterface $index
     * @param Index\Headers $headers
     * @param Math $math
     */
    public function __construct(array $map, BlockIndexInterface $index, Index\Headers $headers, Math $math)
    {
        $this->math = $math;
        $this->chainCache = new ChainCache($map);
        $this->index = $index;
        $this->headers = $headers;
    }

    /**
     * @param BufferInterface $hash
     * @return bool
     */
    public function containsHash(BufferInterface $hash)
    {
        return $this->chainCache->containsHash($hash);
    }

    /**
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetchIndex(BufferInterface $hash)
    {
        if (!$this->chainCache->containsHash($hash)) {
            throw new \RuntimeException('Index by this hash not known');
        }

        return $this->headers->fetch($hash);
    }

    /**
     * @param int $height
     * @return BlockIndexInterface
     */
    public function fetchAncestor($height)
    {
        return $this->fetchIndex($this->getHashFromHeight($height));
    }

    /**
     * @param BufferInterface $txid
     * @return \BitWasp\Bitcoin\Transaction\Transaction
     */
    public function fetchTransaction(Index\Transaction $txIndex, BufferInterface $txid)
    {
        return $txIndex->fetch($this->getIndex()->getHash(), $txid);
    }

    /**
     * @return BlockIndexInterface
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @return ChainCacheInterface
     */
    public function getChainCache()
    {
        return $this->chainCache;
    }

    /**
     * @param BufferInterface $hash
     * @return int
     */
    public function getHeightFromHash(BufferInterface $hash)
    {
        return $this->chainCache->getHeight($hash);
    }

    /**
     * @param int $height
     * @return BufferInterface
     */
    public function getHashFromHeight($height)
    {
        return $this->chainCache->getHash($height);
    }

    /**
     * @param BlockIndexInterface $index
     */
    public function updateTip(BlockIndexInterface $index)
    {
        if ($this->index->getHash() != $index->getHeader()->getPrevBlock()) {
            throw new \RuntimeException('Header: Header does not extend this chain');
        }

        if (($index->getHeight() - 1) != $this->index->getHeight()) {
            throw new \RuntimeException('Header: Incorrect chain height');
        }

        $this->chainCache->add($index);
        $this->index = $index;
        $this->emit('tip', [$index]);
    }
}
