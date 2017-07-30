<?php

namespace BitWasp\Bitcoin\Node\Chain;

use Evenement\EventEmitter;

class ChainSegment extends EventEmitter
{
    /**
     * @var int
     */
    private $segment;

    /**
     * @var int
     */
    private $startHeight;

    /**
     * @var BlockIndexInterface
     */
    private $index;

    /**
     * ChainSegment constructor.
     * @param int $segment
     * @param int $startHeight
     * @param BlockIndexInterface $index
     */
    public function __construct($segment, $startHeight, BlockIndexInterface $index)
    {
        $this->segment = $segment;
        $this->startHeight = $startHeight;
        $this->index = $index;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->segment;
    }

    /**
     * @return int
     */
    public function getStart()
    {
        return $this->startHeight;
    }

    /**
     * @return BlockIndexInterface
     */
    public function getLast()
    {
        return $this->index;
    }

    /**
     * @param BlockIndexInterface $index
     */
    public function next(BlockIndexInterface $index)
    {
        if (!$this->index->isNext($index)) {
            throw new \InvalidArgumentException('Provided Index does not elongate this Chain');
        }

        $this->index = $index;
        $this->emit('tip', [$index]);
    }
}
