<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Node\ChainSegment;
use BitWasp\Bitcoin\Node\ChainView;

class HeadersBatch
{
    /**
     * @var ChainView
     */
    private $chainState;

    /**
     * @var BlockIndexInterface[]
     */
    private $indices;

    /**
     * HeadersBatch constructor.
     * @param ChainView $chainState
     * @param BlockIndexInterface[] $indices
     */
    public function __construct(ChainView $chainState, array $indices)
    {
        $this->chainState = $chainState;
        $this->indices = $indices;
    }

    /**
     * @return ChainView
     */
    public function getTip()
    {
        return $this->chainState;
    }

    /**
     * @return BlockIndexInterface[]
     */
    public function getIndices()
    {
        return $this->indices;
    }
}
