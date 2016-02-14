<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitter;

class Chains extends EventEmitter implements ChainsInterface
{
    /**
     * @var EcAdapterInterface
     */
    private $adapter;

    /**
     * @var ParamsInterface
     */
    private $params;

    /**
     * @var ChainStateInterface[]
     */
    private $states = [];

    /**
     * @var ChainStateInterface
     */
    private $best;

    /**
     * @var BlockIndexInterface
     */
    private $bestIndex;

    /**
     * @param EcAdapterInterface $adapter
     * @param ParamsInterface $params
     */
    public function __construct(EcAdapterInterface $adapter, ParamsInterface $params)
    {
        $this->adapter = $adapter;
        $this->params = $params;
    }

    /**
     * @return ChainStateInterface[]
     */
    public function getStates()
    {
        return $this->states;
    }

    /**
     * @return ChainInterface[]
     */
    public function getChains()
    {
        /** @var ChainInterface[] $chains */
        $chains = [];
        foreach ($this->states as $state) {
            $chains[] = $state->getChain();
        }

        return $chains;
    }

    /**
     * @param ChainStateInterface $a
     * @param ChainStateInterface $b
     * @return int
     */
    public function compareChainStateWork(ChainStateInterface $a, ChainStateInterface $b)
    {
        return $this->adapter->getMath()->cmp(
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
     * @param ChainStateInterface $state
     */
    public function trackState(ChainStateInterface $state)
    {
        $this->states[] = $state;

        // Implement
        /*$state->getChain()->on('tip', function (BlockIndexInterface $index) use ($state) {
            $math = $this->adapter->getMath();
            if ($math->cmp($math->mod($index->getHeight(), $this->params->powRetargetInterval()), 0) === 0) {
                $this->emit('retarget', [$state, $index]);
            }
        });*/
    }

    /**
     * @return ChainStateInterface
     */
    public function best()
    {
        if (null == $this->best) {
            throw new \RuntimeException('No tip known for headers');
        }

        return $this->best;
    }

    /**
     * @param BufferInterface $hash
     * @return false|ChainStateInterface
     */
    public function isKnownHeader(BufferInterface $hash)
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
     * @param BufferInterface $hash
     * @return false|ChainStateInterface
     */
    public function isTip(BufferInterface $hash)
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
