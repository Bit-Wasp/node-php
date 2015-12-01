<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;

class BlockIndex
{
    /**
     * @var string
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
     * @param string $hash
     * @param int $height
     * @param int|string $work
     * @param BlockHeaderInterface $header
     */
    public function __construct($hash, $height, $work, BlockHeaderInterface $header)
    {
        $this->hash = $hash;
        $this->header = $header;
        $this->height = $height;
        $this->work = $work;
    }

    /**
     * @return string
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
