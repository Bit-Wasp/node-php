<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Node\Index\BlockStatus;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitter;

class ChainView extends EventEmitter implements HeaderChainViewInterface
{
    /**
     * @var ChainContainer
     */
    private $container;

    /**
     * @var BlockIndexInterface
     */
    private $blockBestData;

    /**
     * @var BlockIndexInterface
     */
    private $blockBestValid;

    /**
     * @var ChainSegment
     */
    private $segment;

    /**
     * @var ChainSegment[]
     */
    private $segments;

    /**
     * @var GuidedChainView
     */
    private $dataView;

    /**
     * @var GuidedChainView
     */
    private $validView;

    /**
     * ChainView constructor.
     * @param ChainContainer $container
     * @param ChainSegment $segment
     * @param BlockIndexInterface $blockBestData
     * @param BlockIndexInterface $blockBestValid
     */
    public function __construct(ChainContainer $container, ChainSegment $segment, BlockIndexInterface $blockBestData, BlockIndexInterface $blockBestValid)
    {
        $this->container = $container;
        $this->segments = $this->container->getHistory($segment);

        $this->segment = $segment;
        $this->blockBestData = $blockBestData;
        $this->blockBestValid = $blockBestValid;

        $this->dataView = new GuidedChainView($container, $this, $blockBestData, BlockStatus::ACCEPTED);
        $this->validView = new GuidedChainView($container, $this, $blockBestValid, BlockStatus::VALIDATED);
    }

    /**
     * @return GuidedChainView
     */
    public function validBlocks()
    {
        return $this->validView;
    }

    /**
     * @return GuidedChainView
     */
    public function blocks()
    {
        return $this->dataView;
    }
    
    /**
     * @return int|string
     */
    public function count()
    {
        return $this->segment->getLast()->getHeight();
    }

    /**
     * @return ChainSegment
     */
    public function getSegment()
    {
        return $this->segment;
    }

    /**
     * @param BufferInterface $hash
     * @return bool
     */
    public function containsHash(BufferInterface $hash)
    {
        $binary = $hash->getBinary();
        foreach ($this->segments as $segment) {
            $hashes = $this->container->getHashes($segment);
            if (isset($hashes[$binary])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param BufferInterface $buffer
     * @return int
     */
    public function getHeightFromHash(BufferInterface $buffer)
    {
        $binary = $buffer->getBinary();
        foreach ($this->segments as $segment) {
            $hashes = $this->container->getHashes($segment);
            if (isset($hashes[$binary])) {
                return $hashes[$binary];
            }
        }

        throw new \RuntimeException('Hash not found');
    }

    /**
     * @param int $height
     * @return BufferInterface
     */
    public function getHashFromHeight($height)
    {
        for ($i = 0, $l = count($this->segments); $i < $l; $i++) {
            if ($i >= $this->segments[$i]->getStart() && $i <= $this->segments[$i]->getLast()->getHeight()) {
                $r = $this->container->getHeights($this->segments[$i])[$height];
                return new Buffer($r);
            }
        }

        throw new \RuntimeException('Height not found');
    }

    /**
     * @return ChainSegment
     */
    private function latestSegment()
    {
        return end($this->segments);
    }

    /**
     * @return BlockIndexInterface
     */
    public function getIndex()
    {
        return $this->latestSegment()->getLast();
    }

    /**
     * @param BlockIndexInterface $index
     */
    public function updateTip(BlockIndexInterface $index)
    {
        $this->container->updateSegment($this->latestSegment(), $index);
    }

    /**
     * @return ChainSegment[]
     */
    public function getHistory()
    {
        return $this->segments;
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
        if ($height > $this->getSegment()->getLast()->getHeight()) {
            throw new \RuntimeException('Height too great to produce locator');
        }

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
}
