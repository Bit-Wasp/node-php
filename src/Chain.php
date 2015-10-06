<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Math\Math;

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
     * @var string[]
     */
    private $map = [];

    /**
     * @var string[]
     */
    private $reverseMap = [];

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
        $this->map = $map;
        $this->math = $math;
        $this->reverseMap = array_flip($map);
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
     * @return array|\string[]
     */
    public function getMap()
    {
        return $this->map;
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function containsHash($hash)
    {
        return isset($this->reverseMap[$hash]);
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function getHeightFromHash($hash)
    {
        if ($this->containsHash($hash)) {
            return $this->reverseMap[$hash];
        }

        throw new \RuntimeException('Hash not found');
    }

    /**
     * @param int $height
     * @return \string[]
     */
    public function getHashFromHeight($height)
    {
        if (!isset($this->map[$height])) {
            throw new \RuntimeException('fetchhashbyheight: index at this height ('.$height.') not known');
        }

        return $this->map[$height];
    }

    /**
     * @param string $hash
     * @return BlockHeaderInterface
     */
    public function fetchByHash($hash)
    {
        if (!in_array($hash, $this->map)) {
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
            throw new \RuntimeException('Block does not extend this chain');
        }

        if ($index->getHeight() - 1 != $this->index->getHeight()) {
            throw new \RuntimeException('Incorrect chain height');
        }

        $this->index = $index;
        $this->map[] = $index->getHash();
        $this->reverseMap[$index->getHash()] = $index->getHeight();
    }
}