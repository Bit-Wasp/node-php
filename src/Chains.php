<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;

class Chains
{
    /**
     * @var EcAdapterInterface
     */
    private $adapter;

    /**
     * @var ChainState[]
     */
    private $states = [];

    /**
     * @var ChainState
     */
    private $best;

    /**
     * @param EcAdapterInterface $adapter
     */
    public function __construct(EcAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function checkTips()
    {
        $tips = $this->states;
        $sort = function (ChainState $a, ChainState $b) {
            return $this->adapter->getMath()->cmp($a->getHeadersWork(), $b->getHeadersWork());
        };

        usort($tips, $sort);

        $greatestWork = end($tips);
        $this->best = $greatestWork;
        //echo "Setting chain with best headers to:: " . $greatestWork->getChainIndex()->getHash() . " (work: " . $greatestWork->getChainIndex()->getWork() . ") \n\n";
        //echo "                   best block     :: " . $greatestWork->getLastBlock()->getHash() . "\n";
    }

    /**
     * @param string $hash
     * @param callable|null $then
     * @return bool
     */
    public function isTip($hash, callable $then = null)
    {
        foreach ($this->getChains() as $tip) {
            if ($tip->getIndex()->getHash() == $hash) {
                return is_null($then)
                    ? true
                    : $then($tip);
            }
        }

        return false;
    }

    /**
     * @param BlockHeaderInterface $header
     * @return Chain
     */
    public function findTipForNext(BlockHeaderInterface $header)
    {
        foreach ($this->getChains() as $tTip) {
            $tipHash = $tTip->getIndex()->getHash();
            if ($header->getPrevBlock() == $tipHash) {
                $tip = $tTip;
            }
        }

        if (!isset($tip)) {
            throw new \RuntimeException('No tip found for this Header');
        }

        return $tip;
    }

    /**
     * @param ChainState $state
     */
    public function trackChain(ChainState $state)
    {
        $this->states[] = $state;
    }

    /**
     * @return ChainState[]
     */
    public function getStates()
    {
        return $this->states;
    }

    /**
     * @return Chain[]
     */
    public function getChains()
    {
        /** @var Chain[] $chains */
        $chains = [];
        foreach ($this->states as $state) {
            $chains[] = $state->getChain();
        }

        return $chains;
    }

    /**
     * @return ChainState
     */
    public function best()
    {
        if (null == $this->best) {
            throw new \RuntimeException('No tip known for headers');
        }

        return $this->best;
    }
}