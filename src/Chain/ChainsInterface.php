<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Buffertools\Buffer;

interface ChainsInterface
{
    /**
     * @return ChainStateInterface[]
     */
    public function getStates();

    /**
     * @return ChainInterface[]
     */
    public function getChains();

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
     * @param Buffer $hash
     * @return false|ChainStateInterface
     */
    public function isKnownHeader(Buffer $hash);

    /**
     * @param Buffer $hash
     * @return false|ChainStateInterface
     */
    public function isTip(Buffer $hash);
}
