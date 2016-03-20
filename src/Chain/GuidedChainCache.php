<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Buffertools\BufferInterface;

class GuidedChainCache implements ChainCacheInterface
{
    /**
     * @var int
     */
    private $position;
    
    /**
     * @var ChainCacheInterface
     */
    private $traceCache;

    /**
     * GuidedChainCache constructor.
     * @param ChainCacheInterface $traceCache
     */
    public function __construct(ChainCacheInterface $traceCache)
    {
        $this->traceCache = $traceCache;
        $this->position = count($traceCache);
    }

    /**
     * @param BufferInterface $hash
     * @return bool
     */
    public function containsHash(BufferInterface $hash)
    {
        if (!$this->traceCache->containsHash($hash)) {
            return false;
        }

        $lookupHeight = $this->traceCache->getHeight($hash);
        if ($lookupHeight > $this->position) {
            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->position;
    }

    /**
     * @param int $height
     * @return BufferInterface
     */
    public function getHash($height)
    {
        if ($height > $this->position) {
            throw new \RuntimeException('GuidedChainCache: index at this height (' . $height . ') not known');
        }

        return $this->traceCache->getHash($height);
    }

    /**
     * @param BufferInterface $hash
     * @return int
     */
    public function getHeight(BufferInterface $hash)
    {
        if (!$this->containsHash($hash)) {
            throw new \RuntimeException('Hash not found');
        }

        return $this->traceCache->getHeight($hash);
    }

    /**
     * @return BufferInterface
     */
    private function getNextHash()
    {
        return $this->traceCache->getHash($this->position + 1);
    }

    /**
     * @param BlockIndexInterface $index
     */
    public function add(BlockIndexInterface $index)
    {
        if (!$index->getHash()->equals($this->getNextHash())) {
            throw new \RuntimeException('GuidedChainCache: BlockIndex does not match this Chain');
        }

        $this->position++;
    }

    /**
     * @param int $endHeight
     * @return ChainCacheInterface|mixed
     */
    public function subset($endHeight)
    {
        if ($endHeight > $this->position) {
            throw new \InvalidArgumentException('GuidedChainCache::subset() - end height exceeds size of this cache');
        }

        return $this->subset($endHeight);
    }
}
