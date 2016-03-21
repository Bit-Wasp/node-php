<?php
namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;

interface ConsensusInterface
{
    /**
     * @return ParamsInterface
     */
    public function getParams();

    /**
     * @param int|string $amount
     * @return bool
     */
    public function checkAmount($amount);

    /**
     * @param int $height
     * @return int|string
     */
    public function getSubsidy($height);

    /**
     * @param BlockIndexInterface $prevIndex
     * @param int $timeFirstBlock
     * @return int|string
     */
    public function calculateNextWorkRequired(BlockIndexInterface $prevIndex, $timeFirstBlock);
}
