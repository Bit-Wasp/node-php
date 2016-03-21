<?php

namespace BitWasp\Bitcoin\Node\Index\Validation;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Chain\Forks;
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
     * @param ChainStateInterface $chain
     * @param BlockIndexInterface $index
     * @param BlockIndexInterface $prevIndex
     * @param Forks $forks
     * @return $this
     */
    public function checkContextual(ChainStateInterface $chain, BlockIndexInterface $index, BlockIndexInterface $prevIndex, Forks $forks);
}
