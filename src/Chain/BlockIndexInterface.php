<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Buffertools\BufferInterface;

interface BlockIndexInterface
{
    /**
     * @return BufferInterface
     */
    public function getHash();

    /**
     * @return int|string
     */
    public function getHeight();

    /**
     * @return int|string
     */
    public function getWork();

    /**
     * @return BlockHeaderInterface
     */
    public function getHeader();
}
