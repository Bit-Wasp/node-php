<?php

namespace BitWasp\Bitcoin\Node\Index;


use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Chain\BlockData;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\DbInterface;
class UtxoDb
{
    /**
     * @var Math
     */
    private $math;

    /**
     * @var DbInterface
     */
    private $db;

    /**
     * UtxoDb constructor.
     * @param DbInterface $db
     * @param Math $math
     */
    public function __construct(DbInterface $db, Math $math)
    {
        $this->math = $math;
        $this->db = $db;
    }

    /**
     * @param ChainStateInterface $chainState
     * @param BlockInterface $block
     * @param BlockData $blockData
     */
    public function update(ChainStateInterface $chainState, BlockInterface $block, BlockData $blockData)
    {
        $this->db->updateUtxoSet($blockData->requiredOutpoints, $blockData->remainingNew);
    }

}