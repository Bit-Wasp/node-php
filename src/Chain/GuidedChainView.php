<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitter;

class GuidedChainView extends EventEmitter implements ChainViewInterface
{
    /**
     * @var ChainContainer
     */
    private $container;
    
    /**
     * @var BlockIndexInterface
     */
    private $index;

    /**
     * @var ChainView
     */
    private $view;

    /**
     * @var int
     */
    private $position;

    /**
     * GuidedChainView constructor.
     * @param ChainContainer $container
     * @param ChainView $view
     * @param BlockIndexInterface $lead
     */
    public function __construct(ChainContainer $container, ChainView $view, BlockIndexInterface $lead)
    {
        $this->container = $container;
        $this->view = $view;
        $this->position = $lead->getHeight();
        $this->index = $lead;
    }

    /**
     * @param BlockIndexInterface $index
     */
    public function updateTip(BlockIndexInterface $index)
    {
        $this->container->updateSegmentBlock($this->view->getSegment(), $index);
        $this->position++;
        $this->index = $index;
    }

    /**
     * @return BlockIndexInterface
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @return ChainSegment
     */
    public function getSegment()
    {
        return $this->view->getSegment();
    }

    /**
     * @return ChainSegment[]
     */
    public function getHistory()
    {
        $height = $this->index->getHeight();
        $history = [];
        foreach ($this->getHistory() as $segment) {
            if ($segment->getLast()->getHeight() <= $height) {
                $history[] = $segment;
            } else {
                break;
            }
        }

        return $history;
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->position + 1;
    }

    /**
     * @param int $height
     * @return BufferInterface
     */
    public function getHashFromHeight($height)
    {
        if ($height > $this->position) {
            throw new \RuntimeException('GuidedChainCache: index at this height (' . $height . ') not known');
        }

        return $this->view->getHashFromHeight($height);
    }

    /**
     * @param BufferInterface $hash
     * @return bool
     */
    public function containsHash(BufferInterface $hash)
    {
        if (!$this->view->containsHash($hash)) {
            return false;
        }

        $lookupHeight = $this->view->getHeightFromHash($hash);
        if ($lookupHeight > $this->position) {
            return false;
        }

        return true;
    }


    /**
     * @param BufferInterface $hash
     * @return int
     */
    public function getHeightFromHash(BufferInterface $hash)
    {
        if (!$this->containsHash($hash)) {
            throw new \RuntimeException('Hash not found');
        }

        return $this->view->getHeightFromHash($hash);
    }

    /**
     * Produce a block locator for a given block height.
     * @param int $height
     * @param BufferInterface|null $final
     * @return BlockLocator
     */
    public function getLocator($height, BufferInterface $final = null)
    {
        $step = 1;
        $hashes = [];
        $headerHash = $this->getHashFromHeight($height);

        while (true) {
            $hashes[] = $headerHash;
            if ($height === 0) {
                break;
            }

            $height = max($height - $step, 0);
            $headerHash = $this->getHashFromHeight($height);
            if (count($hashes) >= 10) {
                $step *= 2;
            }
        }

        if (null === $final) {
            $hashStop = new Buffer('', 32);
        } else {
            $hashStop = $final;
        }

        return new BlockLocator(
            $hashes,
            $hashStop
        );
    }

    /**
     * @param BufferInterface|null $hashStop
     * @return BlockLocator
     */
    public function getHeadersLocator(BufferInterface $hashStop = null)
    {
        return $this->getLocator($this->getIndex()->getHeight(), $hashStop);
    }

    /**
     * @param BufferInterface|null $hashStop
     * @return BlockLocator
     */
    public function getBlockLocator(BufferInterface $hashStop = null)
    {
        return $this->getLocator($this->getIndex()->getHeight(), $hashStop);
    }
}