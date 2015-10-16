<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Buffertools\Buffer;

class ChainState
{
    /**
     * @var Chain
     */
    private $chain;

    /**
     * @var BlockIndex
     */
    private $lastBlock;

    /**
     * @param Math $math
     * @param Chain $chain
     * @param BlockIndex $lastBlock
     */
    public function __construct(Math $math, Chain $chain, BlockIndex $lastBlock)
    {
        $this->chain = $chain;
        $this->lastBlock = $lastBlock;
    }

    /**
     * @param BlockIndex $blockIndex
     */
    public function updateTip(BlockIndex $blockIndex)
    {
        $this->chain->updateTip($blockIndex);
    }

    /**
     * @param BlockIndex $index
     */
    public function updateLastBlock(BlockIndex $index)
    {
        if ($this->lastBlock->getHash() !== $index->getHeader()->getPrevBlock()) {
            throw new \RuntimeException('UpdateLastBlock: Block does not extend this chain');
        }

        if ($this->lastBlock->getHeight() != $index->getHeight() - 1) {
            throw new \RuntimeException('UpdateLastBlock: Incorrect chain height');
        }

        $this->lastBlock = $index;
    }

    /**
     * @return Chain
     */
    public function getChain()
    {
        return $this->chain;
    }

    /**
     * @return BlockIndex
     */
    public function getChainIndex()
    {
        return $this->chain->getIndex();
    }

    /**
     * @return BlockIndex
     */
    public function getLastBlock()
    {
        return $this->lastBlock;
    }

    /**
     * @return ChainCache
     */
    public function bestBlocksCache()
    {
        return $this->chain
            ->getChainCache()
            ->subset($this->lastBlock->getHeight());
    }

    /**
     * @return int|string
     */
    public function blocksLeftToSync()
    {
        return ($this->chain->getIndex()->getHeight() - $this->lastBlock->getHeight());
    }

    /**
     * Produce a block locator for a given block height.
     * @param int $height
     * @param Buffer|null $final
     * @return BlockLocator
     */
    public function getLocator($height, Buffer $final = null)
    {
        $step = 1;
        $hashes = [];
        $headerHash = $this->chain->getHashFromHeight($height);

        $h = [];
        while (true) {
            $hashes[] = Buffer::hex($headerHash, 32);
            $h[$height] = $headerHash;
            if ($height == 0) {
                break;
            }

            $height = max($height - $step, 0);
            $headerHash = $this->chain->getHashFromHeight($height);
            if (count($hashes) >= 10) {
                $step *= 2;
            }
        }

        if (is_null($final)) {
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
     * @param Buffer|null $hashStop
     * @return BlockLocator
     */
    public function getHeadersLocator(Buffer $hashStop = null)
    {
        echo "Produce Headers locator (".$this->chain->getIndex()->getHeight().") \n";
        return $this->getLocator($this->chain->getIndex()->getHeight(), $hashStop);
    }

    /**
     * @param Buffer|null $hashStop
     * @return BlockLocator
     */
    public function getBlockLocator(Buffer $hashStop = null)
    {
        echo "Produce Block locator (".$this->lastBlock->getHeight().") \n";
        return $this->getLocator($this->lastBlock->getHeight() - 1, $hashStop);
    }

}