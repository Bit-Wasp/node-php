<?php

namespace BitWasp\Bitcoin\Node\Chain;


interface HeaderChainViewInterface extends ChainViewInterface
{

    /**
     * @return GuidedChainView
     */
    public function blocks();

    /**
     * @return GuidedChainView
     */
    public function validBlocks();
}
