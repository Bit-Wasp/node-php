<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\ChainSegment;
use BitWasp\Bitcoin\Node\ChainView;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitterInterface;

interface ChainsInterface extends \Countable, EventEmitterInterface
{

    /**
     * @param Math $math
     * @return ChainView
     */
    public function best(Math $math);

    /**
     * @param ChainSegment $segment
     * @return ChainViewInterface
     */
    public function view(ChainSegment $segment);

    /**
     * @param ChainViewInterface $view
     * @return ChainAccessInterface
     */
    public function access(ChainViewInterface $view);

    /**
     * @param ChainSegment $segment
     * @return GuidedChainView
     */
    public function blocks(ChainSegment $segment);

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
