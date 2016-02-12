<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Buffertools\BufferInterface;

interface HeaderCheckInterface
{
    /**
     * @param BufferInterface $hash
     * @param BlockHeaderInterface $header
     * @param bool $checkPow
     * @return $this
     */
    public function check(BufferInterface $hash, BlockHeaderInterface $header, $checkPow = true);

    /**
     * @param ChainStateInterface $state
     * @param BlockHeaderInterface $header
     * @return $this
     */
    public function checkContextual(ChainStateInterface $state, BlockHeaderInterface $header);

    /**
     * @param BlockIndexInterface $prevIndex
     * @param BufferInterface $hash
     * @param BlockHeaderInterface $header
     * @return BlockIndexInterface
     */
    public function makeIndex(BlockIndexInterface $prevIndex, BufferInterface $hash, BlockHeaderInterface $header);
}
