<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Buffertools\Buffer;
use Evenement\EventEmitter;

class Chains extends EventEmitter
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
     * @var BlockIndex
     */
    private $bestIndex;

    /**
     * @param EcAdapterInterface $adapter
     */
    public function __construct(EcAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @param ChainState $a
     * @param ChainState $b
     * @return int
     */
    public function compareChainStateWork(ChainState $a, ChainState $b)
    {
        return $this->adapter->getMath()
            ->cmp(
                $a->getChain()->getIndex()->getWork(),
                $b->getChain()->getIndex()->getWork()
            );
    }

    /**
     *
     */
    public function checkTips()
    {
        $tips = $this->states;
        usort($tips, array($this, 'compareChainStateWork'));

        $greatestWork = end($tips);
        /** @var ChainState $greatestWork */
        if (!isset($this->best) || $this->bestIndex !== $greatestWork->getChainIndex()) {
            $this->best = $greatestWork;
            $this->bestIndex = $greatestWork->getChainIndex();
            $this->emit('newtip', [$greatestWork]);
        }
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

    /**
     * @param Buffer $hash
     * @return false|ChainState
     */
    public function isKnownHeader(Buffer $hash)
    {
        return array_reduce($this->states, function ($foundState, ChainState $state) use ($hash) {
            if ($foundState instanceof ChainState) {
                return $foundState;
            }

            if ($state->getChain()->containsHash($hash)) {
                return $state;
            }

            return false;
        });
    }

    /**
     * @param Buffer $hash
     * @return false|ChainState
     */
    public function isTip(Buffer $hash)
    {
        return array_reduce($this->states, function ($foundState, ChainState $state) use ($hash) {
            if ($foundState instanceof ChainState) {
                return $foundState;
            }

            if ($state->getChainIndex()->getHash() == $hash) {
                return $state;
            }

            return false;
        });
    }
}
