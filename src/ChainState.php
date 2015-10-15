<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
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
            print_r($this->lastBlock);
            print_r($index);
            die('Block does not extend this chain');
            throw new \RuntimeException('Block does not extend this chain');
        }


        if ($this->lastBlock->getHeight() != $index->getHeight() - 1) {
            var_dump($this->lastBlock->getHeight());
            var_dump($index->getHeight());
            var_dump($index->getHeight() - 1);
            print_r($this->lastBlock);
            print_r($index);
            die();
            throw new \RuntimeException('Incorrect chain height');
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

    public function calculateNextWorkRequired(BlockIndex $indexLast, $timeFirstBlock)
    {
        $math = Bitcoin::getMath();
        $header = $indexLast->getHeader();
        $timespan = $math->sub($header->getTimestamp(), $timeFirstBlock);

        $lowest = $math->div($this->params->targetTimespan(), 4);
        $highest = $math->mul($this->params->targetTimespan(), 4);
        if ($math->cmp($timespan, $lowest) < 0) {
            $timespan = $lowest;
        }
        if ($math->cmp($timespan, $highest) > 0) {
            $timespan = $highest;
        }
        $new = $math->unpackCompact($header->getBits());
        $new = bcdiv(bcmul($new, $timespan), $this->params->targetTimespan());
        if ($math->cmp($new, $this->params->getPowLimit()) > 0) {
            return $this->params->getPowLimit();
        }

        //return $math->getCompact($new);
    }

    /**
     * @param BlockIndex $indexLast
     * @param BlockHeaderInterface $header
     * @return Buffer
     */
    public function getWorkRequired(BlockIndex $indexLast, BlockHeaderInterface $header)
    {
        $powLimitBits = $this->difficulty->lowestBits();
        if ($indexLast == null) {
            return $powLimitBits;
        }

        // Maybe there's no change in difficulty
        if (($indexLast->getHeight() + 1) % $this->params->difficultyAdjustmentInterval() == 0) {
            return $indexLast->getHeader()->getBits();
        }

        // Retarget
        $math = $this->adapter->getMath();
        $heightLastRetarget = $math->sub($indexLast->getHeight(), $math->sub($this->params->difficultyAdjustmentInterval(), 1));
        $indexLastRetarget = $this->fetchByHeight($heightLastRetarget);
    }
}