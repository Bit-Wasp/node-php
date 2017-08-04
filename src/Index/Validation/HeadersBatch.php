<?php

namespace BitWasp\Bitcoin\Node\Index\Validation;

use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\HeaderChainViewInterface;

class HeadersBatch
{
    /**
     * @var HeaderChainViewInterface
     */
    private $chainState;

    /**
     * @var BlockIndexInterface[]
     */
    private $indices;

    /**
     * HeadersBatch constructor.
     * @param HeaderChainViewInterface $chainState
     * @param BlockIndexInterface[] $indices
     */
    public function __construct(HeaderChainViewInterface $chainState, array $indices)
    {
        $this->chainState = $chainState;
        $this->indices = $indices;
    }

    /**
     * @return HeaderChainViewInterface
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
