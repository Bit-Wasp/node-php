<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Buffertools\BufferInterface;

class BlockIndex implements BlockIndexInterface
{
    /**
     * @var BufferInterface
     */
    private $hash;

    /**
     * @var int|string
     */
    private $height;

    /**
     * @var int|string
     */
    private $work;

    /**
     * @var BlockHeaderInterface
     */
    private $header;

    /**
     * @param BufferInterface $hash
     * @param int $height
     * @param int|string $work
     * @param BlockHeaderInterface $header
     */
    public function __construct(BufferInterface $hash, $height, $work, BlockHeaderInterface $header)
    {
        $this->hash = $hash;
        $this->header = $header;
        $this->height = $height;
        $this->work = $work;
    }

    /**
     * @return BufferInterface
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @return int|string
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @return int|string
     */
    public function getWork()
    {
        return $this->work;
    }

    /**
     * @return BlockHeaderInterface
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @param BlockIndexInterface $index
     * @return bool
     */
    public function isNext(BlockIndexInterface $index)
    {
        if (false === $this->hash->equals($index->getHeader()->getPrevBlock())) {
            return false;
        }

        if (false === ($index->getHeight() == ($this->height + 1))) {
            return false;
        }

        return true;
    }
}
