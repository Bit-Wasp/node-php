<?php

namespace BitWasp\Bitcoin\Node\Chain;

class HeadersBatch
{
    /**
     * @var ChainStateInterface
     */
    private $chainState;

    /**
     * @var BlockIndexInterface[]
     */
    private $indices;

    /**
     * HeadersBatch constructor.
     * @param ChainStateInterface $chainState
     * @param BlockIndexInterface[] $indices
     */
    public function __construct(ChainStateInterface $chainState, array $indices)
    {
        $this->chainState = $chainState;
        $this->indices = $indices;
    }

    /**
     * @return ChainStateInterface
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
