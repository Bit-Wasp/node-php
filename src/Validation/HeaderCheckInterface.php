<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Buffertools\Buffer;

interface HeaderCheckInterface
{
    /**
     * @param Buffer $hash
     * @param BlockHeaderInterface $header
     * @param bool $checkPow
     * @return $this
     */
    public function check(Buffer $hash, BlockHeaderInterface $header, $checkPow = true);

    /**
     * @param ChainState $state
     * @param BlockHeaderInterface $header
     * @return $this
     */
    public function checkContextual(ChainState $state, BlockHeaderInterface $header);

    /**
     * @param BlockIndexInterface $prevIndex
     * @param Buffer $hash
     * @param BlockHeaderInterface $header
     * @return BlockIndexInterface
     */
    public function makeIndex(BlockIndexInterface $prevIndex, Buffer $hash, BlockHeaderInterface $header);
}
