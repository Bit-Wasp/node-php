<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Buffertools\Buffer;

class BlockIndex
{
    /**
     * @var Buffer
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
     * @param Buffer $hash
     * @param int $height
     * @param int|string $work
     * @param BlockHeaderInterface $header
     */
    public function __construct(Buffer $hash, $height, $work, BlockHeaderInterface $header)
    {
        $this->hash = $hash;
        $this->header = $header;
        $this->height = $height;
        $this->work = $work;
    }

    /**
     * @return Buffer
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
}
