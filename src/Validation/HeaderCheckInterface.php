<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\ChainState;

interface HeaderCheckInterface
{
    /**
     * @param BlockHeaderInterface $header
     * @param bool|true $checkPow
     * @return $this
     */
    public function check(BlockHeaderInterface $header, $checkPow = true);

    /**
     * @param ChainState $state
     * @param BlockHeaderInterface $header
     * @return $this
     */
    public function checkContextual(ChainState $state, BlockHeaderInterface $header);

    /**
     * @param BlockIndex $prevIndex
     * @param BlockHeaderInterface $header
     * @return BlockIndex
     */
    public function makeIndex(BlockIndex $prevIndex, BlockHeaderInterface $header);
}
