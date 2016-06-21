<?php

namespace BitWasp\Bitcoin\Node\Chain;


interface HeaderChainViewInterface extends ChainViewInterface
{
    /**
     * @return BlockIndexInterface
     */
    public function getLastBlock();

    /**
     * @return GuidedChainView
     */
    public function blocks();
}