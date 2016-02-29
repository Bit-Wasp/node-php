<?php

namespace BitWasp\Bitcoin\Node\Index\Validation;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainInterface;
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
     * @param ChainInterface $chain
     * @param BlockIndexInterface $index
     * @param BlockIndexInterface $prevIndex
     * @param Forks $forks
     * @return $this
     */
    public function checkContextual(ChainInterface $chain, BlockIndexInterface $index, BlockIndexInterface $prevIndex, Forks $forks);

    /**
     * @param BlockIndexInterface $prevIndex
     * @param BufferInterface $hash
     * @param BlockHeaderInterface $header
     * @return BlockIndexInterface
     */
    public function makeIndex(BlockIndexInterface $prevIndex, BufferInterface $hash, BlockHeaderInterface $header);
}
