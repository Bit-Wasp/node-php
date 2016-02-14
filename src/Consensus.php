<?php

namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Amount;
use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;

class Consensus implements ConsensusInterface
{

    /**
     * @var Math
     */
    private $math;

    /**
     * @var ParamsInterface
     */
    private $params;

    /**
     * @param Math $math
     * @param ParamsInterface $params
     */
    public function __construct(Math $math, ParamsInterface $params)
    {
        $this->math = $math;
        $this->params = $params;
    }

    /**
     * @return ParamsInterface
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param int|string $amount
     * @return bool
     */
    public function checkAmount($amount)
    {
        return $this->math->cmp($amount, $this->math->mul($this->params->maxMoney(), Amount::COIN)) < 0;
    }

    /**
     * @param int $height
     * @return int|string
     */
    public function getSubsidy($height)
    {
        $math = $this->math;
        $halvings = $math->div($height, $this->params->subsidyHalvingInterval());
        if ($math->cmp($halvings, 64) >= 0) {
            return 0;
        }

        $subsidy = $math->mul(50, Amount::COIN);
        $subsidy = $math->rightShift($subsidy, $halvings);
        return $subsidy;
    }

    /**
     * @param BlockIndexInterface $prevIndex
     * @param int $timeFirstBlock
     * @return int|string
     */
    public function calculateNextWorkRequired(BlockIndexInterface $prevIndex, $timeFirstBlock)
    {
        $header = $prevIndex->getHeader();
        $math = $this->math;
        $timespan = $math->sub($header->getTimestamp(), $timeFirstBlock);

        $lowest = $math->div($this->params->powTargetTimespan(), 4);
        $highest = $math->mul($this->params->powTargetTimespan(), 4);

        if ($math->cmp($timespan, $lowest) < 0) {
            $timespan = $lowest;
        }

        if ($math->cmp($timespan, $highest) > 0) {
            $timespan = $highest;
        }

        $negative = false;
        $overflow = false;
        $target = $math->writeCompact($header->getBits()->getInt(), $negative, $overflow);
        $limit = $this->math->writeCompact($this->params->powBitsLimit(), $negative, $overflow);
        $new = bcdiv(bcmul($target, $timespan), $this->params->powTargetTimespan());
        if ($math->cmp($new, $limit) > 0) {
            $new = $limit;
        }

        return $math->parseCompact($new, false);

    }

    /**
     * @param ChainInterface $chain
     * @param BlockIndexInterface $prevIndex
     * @return int|string
     */
    public function getWorkRequired(ChainInterface $chain, BlockIndexInterface $prevIndex)
    {
        $math = $this->math;

        if ($math->cmp($math->mod($math->add($prevIndex->getHeight(), 1), $this->params->powRetargetInterval()), 0) !== 0) {
            // No change in difficulty
            return $prevIndex->getHeader()->getBits()->getInt();
        }

        // Retarget
        $heightLastRetarget = $math->sub($prevIndex->getHeight(), $math->sub($this->params->powRetargetInterval(), 1));
        $lastTime = $chain->fetchAncestor($heightLastRetarget)->getHeader()->getTimestamp();
        return $this->calculateNextWorkRequired($prevIndex, $lastTime);
    }

    /**
     * @param ChainStateInterface $state
     * @return int|string
     */
    public function getWorkForNextTip(ChainStateInterface $state)
    {
        return $this->getWorkRequired($state->getChain(), $state->getChainIndex());
    }
}
