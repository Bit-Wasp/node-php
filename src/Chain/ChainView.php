<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Chain\BlockLocator;
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
    private $block;

    /**
     * @var ChainSegment
     */
    private $segment;

    /**
     * @var ChainSegment[]
     */
    private $segments;

    /**
     * @var array
     */
    private $heightMap = [];

    /**
     * @var GuidedChainView
     */
    private $blockView;
    
    /**
     * ChainView constructor.
     * @param ChainContainer $container
     * @param ChainSegment $segment
     * @param BlockIndexInterface $block
     */
    public function __construct(ChainContainer $container, ChainSegment $segment, BlockIndexInterface $block)
    {
        $this->container = $container;
        $this->segments = $this->container->getHistory($segment);
        $this->segment = $segment;
        $this->block = $block;
        foreach ($this->segments as $segment) {
            $heights = $container->getHeights($segment);
            $this->heightMap = array_merge($this->heightMap, $heights);
        }

        $this->blockView = new GuidedChainView($container, $this, $block);
    }

    /**
     * @return GuidedChainView
     */
    public function blocks()
    {
        return $this->blockView;
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
        if (isset($this->heightMap[$height])) {
            return new Buffer($this->heightMap[$height], 32);
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
        $this->heightMap[$index->getHeight()] = $index->getHash()->getBinary();
    }

    /**
     * @return BlockIndexInterface
     */
    public function getLastBlock()
    {
        return $this->block;
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
        $map = $this->heightMap;
        if ($height > count($map)) {
            throw new \RuntimeException('Height too great to produce locator');
        }

        $headerHash = new Buffer($map[$height]);

        while (true) {
            $hashes[] = $headerHash;
            if ($height === 0) {
                break;
            }

            $height = max($height - $step, 0);
            $headerHash = new Buffer($map[$height], 32);
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