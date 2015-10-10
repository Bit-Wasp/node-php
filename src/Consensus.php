<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Amount;
use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionInputCollection;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Transaction\TransactionOutputCollection;
use BitWasp\Buffertools\Buffer;

class Consensus
{
    const P2SH_ACTIVATION = '1333238400';

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