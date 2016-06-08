<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitter;

class ChainView extends EventEmitter implements ChainViewInterface
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
        foreach ($this->segments as $segment) {
            if ($height >= $segment->getStart() && $height <= $segment->getLast()->getHeight()) {
                $hashes = $this->container->getHashes($segment);
                $heightMap = array_flip($hashes);
                return new Buffer($heightMap[$height], 32);
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
     * @return BlockIndexInterface
     */
    public function getLastBlock()
    {
        return $this->block;
    }

    private function heightMap()
    {
        $map = [];
        foreach ($this->segments as $segment) {
            $hashes = $this->container->getHashes($segment);
            $map = array_merge($map, array_flip($hashes));
        }

        return $map;
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
        $map = $this->heightMap();
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

    /**
     * @param BufferInterface|null $hashStop
     * @return BlockLocator
     */
    public function getBlockLocator(BufferInterface $hashStop = null)
    {
        return $this->getLocator($this->getIndex()->getHeight(), $hashStop);
    }
}