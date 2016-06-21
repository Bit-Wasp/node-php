<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Math\Math;

class ChainWorkComparator
{
    /**
     * @var Math
     */
    private $math;

    /**
     * ChainWorkComparator constructor.
     * @param Math $math
     */
    public function __construct(Math $math)
    {
        $this->math = $math;
    }

    /**
     * @param ChainSegment $a
     * @param ChainSegment $b
     * @return int
     */
    public function __invoke(ChainSegment $a, ChainSegment $b)
    {
        return $this->math->cmp(gmp_init($a->getLast()->getWork(), 10), gmp_init($b->getLast()->getWork(), 10));
    }
}