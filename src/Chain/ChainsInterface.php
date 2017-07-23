<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitterInterface;

interface ChainsInterface extends \Countable, EventEmitterInterface
{

    /**
     * @return ChainView
     */
    public function best();

    /**
     * @param ChainSegment $segment
     * @return ChainViewInterface
     */
    public function view(ChainSegment $segment);

    /**
     * @param ChainSegment $segment
     * @param BlockIndexInterface $index
     */
    public function updateSegment(ChainSegment $segment, BlockIndexInterface $index);
    
    /**
     * @param ChainSegment $segment
     * @param BlockIndexInterface $index
     */
    public function updateSegmentBlock(ChainSegment $segment, BlockIndexInterface $index);
    
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
     * @param ChainViewInterface $view
     * @return GuidedChainView
     */
    public function blocksView(ChainViewInterface $view);

    /**
     * @param BufferInterface $hash
     * @return false|ChainViewInterface
     */
    public function isKnownHeader(BufferInterface $hash);

    /**
     * @return ChainSegment[]
     */
    public function getSegments();

    /**
     * @param BufferInterface $hash
     * @return false|ChainViewInterface
     */
    public function isTip(BufferInterface $hash);
}
