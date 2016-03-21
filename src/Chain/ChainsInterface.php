<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitterInterface;

interface ChainsInterface extends \Countable, EventEmitterInterface
{
    /**
     * @return ChainStateInterface[]
     */
    public function getStates();

    /**
     * @param ChainStateInterface $a
     * @param ChainStateInterface $b
     * @return int
     */
    public function compareChainStateWork(ChainStateInterface $a, ChainStateInterface $b);

    /**
     * @return void
     */
    public function checkTips();

    /**
     * @param ChainStateInterface $state
     */
    public function trackState(ChainStateInterface $state);

    /**
     * @return ChainStateInterface
     */
    public function best();

    /**
     * @param BufferInterface $hash
     * @return false|ChainStateInterface
     */
    public function isKnownHeader(BufferInterface $hash);

    /**
     * @param BufferInterface $hash
     * @return false|ChainStateInterface
     */
    public function isTip(BufferInterface $hash);
}
