<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Amount;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Math\Math;

class Consensus
{
    const P2SH_ACTIVATION = '1333238400';

    /**
     * @var Math
     */
    private $math;

    /**
     * @var Params
     */
    private $params;

    /**
     * @param Math $math
     * @param Params $params
     */
    public function __construct(Math $math, Params $params)
    {
        $this->math = $math;
        $this->params = $params;
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
     * @param Index\Blocks $blocks
     * @param int $currentHeight
     * @return bool
     */
    public function scriptVerifyPayToScriptHash(Index\Blocks $blocks, $currentHeight)
    {
        return $this->math->cmp($blocks->fetchByHeight($currentHeight)->getHeader()->getTimestamp(), self::P2SH_ACTIVATION) >= 0;
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