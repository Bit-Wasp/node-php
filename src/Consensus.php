<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Amount;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\ChainState;

class Consensus
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
     * @param BlockIndex $prevIndex
     * @param $timeFirstBlock
     * @return int|string
     */
    public function calculateNextWorkRequired(BlockIndex $prevIndex, $timeFirstBlock)
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
        $target = $math->compact()->set($header->getBits()->getInt(), $negative, $overflow);
        $limit = $this->math->compact()->set($this->params->powBitsLimit(), $negative, $overflow);
        $new = bcdiv(bcmul($target, $timespan), $this->params->powTargetTimespan());
        if ($math->cmp($new, $limit) > 0) {
            $new = $limit;
        }

        return $math->compact()->read($new, false);

    }

    /**
     * @param ChainState $state
     * @return int|string
     */
    public function getWorkRequired(ChainState $state)
    {
        $math = $this->math;
        $index = $state->getChain()->getIndex();
        if ($math->cmp($math->mod($math->add($index->getHeight(), 1), $this->params->powRetargetInterval()), 0) != 0) {
            // No change in difficulty
            return $index->getHeader()->getBits()->getInt();
        }

        // Retarget
        $heightLastRetarget = $math->sub($index->getHeight(), $math->sub($this->params->powRetargetInterval(), 1));

        $lastTime = $state->getChain()->fetchAncestor($heightLastRetarget)->getHeader()->getTimestamp();

        return $this->calculateNextWorkRequired($index, $lastTime);
    }

    /**
     * @param int|string $currentTime
     * @return bool
     */
    public function scriptVerifyPayToScriptHash($currentTime)
    {
        return $this->math->cmp($currentTime, $this->params->p2shActivateTime()) >= 0;
    }

    /**
     * @param Index\Blocks $blocks
     * @param int $currentHeight
     * @param BlockHeaderInterface $header
     * @return bool
     */
    public function scriptVerifyDerSig(Index\Blocks $blocks, $currentHeight, BlockHeaderInterface $header)
    {
        if ($this->math->cmp($header->getVersion(), 3)
            && $this->isSuperMajority(3, $currentHeight - 1, $blocks, $this->params->majorityEnforceBlockUpgrade())
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param int $minVersion
     * @param int $startHeight
     * @param int $nRequired
     * @param Index\Blocks $blocks
     * @return bool
     */
    public function isSuperMajority($minVersion, $startHeight, Index\Blocks $blocks, $nRequired)
    {
        $nFound = 0;
        $window = $this->params->majorityWindow();
        for ($i = 0; $i < $window && $nFound < $nRequired && $index = $blocks->fetchByHeight($startHeight - $i); $i++) {
            if ($this->math->cmp($index->getHeader()->getVersion(), $minVersion)) {
                $nFound++;
            }
        }

        return $nFound >= $nRequired;
    }
}