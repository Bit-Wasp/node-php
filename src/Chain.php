<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Math\Math;

/**
 * This class retains all of this in memory. It must be
 * rebuilt on startup.
 */
class Chain
{
    /**
     * @var Index\Headers
     */
    private $headers;

    /**
     * @var BlockIndex
     */
    private $index;

    /**
     * @var ChainCache
     */
    private $chainCache;

    /**
     * @var Math
     */
    private $math;

    /**
     * @param string[] $map
     * @param BlockIndex $index
     * @param Index\Headers $headers
     * @param Math $math
     */
    public function __construct(array $map, BlockIndex $index, Index\Headers $headers, Math $math)
    {
        $this->math = $math;
        $this->chainCache = new ChainCache($map);
        $this->index = $index;
        $this->headers = $headers;
    }

    /**
     * @return BlockIndex
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @return ChainCache
     */
    public function getChainCache()
    {
        return $this->chainCache;
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function containsHash($hash)
    {
        return $this->chainCache->containsHash($hash);
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function getHeightFromHash($hash)
    {
        return $this->chainCache->getHeight($hash);
    }

    /**
     * @param int $height
     * @return \string[]
     */
    public function getHashFromHeight($height)
    {
        return $this->chainCache->getHash($height);
    }

    /**
     * @param string $hash
     * @return BlockIndex
     */
    public function fetchByHash($hash)
    {
        if (!$this->chainCache->containsHash($hash)) {
            throw new \RuntimeException('Index by this hash not known');
        }

        return $this->headers->fetchByHash($hash);
    }

    /**
     * @param BlockIndex $index
     */
    public function updateTip(BlockIndex $index)
    {
        if ($this->index->getHash() !== $index->getHeader()->getPrevBlock()) {
            throw new \RuntimeException('Header does not extend this chain');
        }

        if ($index->getHeight() - 1 != $this->index->getHeight()) {
            throw new \RuntimeException('Incorrect chain height');
        }

        $this->chainCache->add($index);
        $this->index = $index;
    }

    /**
     * @param int $height
     * @return BlockIndex
     */
    public function fetchAncestor($height)
    {
        return $this->fetchByHash($this->getHashFromHeight($height));
    }
}