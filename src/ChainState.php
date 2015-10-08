<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Chain\BlockLocator;
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
     * @param Chain $chain
     * @param BlockIndex $lastBlock
     */
    public function __construct(Chain $chain, BlockIndex $lastBlock)
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
        if ($index->getHeader()->getPrevBlock() !== $this->lastBlock->getHash()) {
            throw new \InvalidArgumentException('Index does not elongate chain');
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
     * @return int|string
     */
    public function getHeadersWork()
    {
        return $this->chain->getIndex()->getWork();
    }

    /**
     * @return int|string
     */
    public function getBlocksWork()
    {
        return $this->lastBlock->getWork();
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
        echo "Produce locator ($height) \n";
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
        return $this->getLocator($this->chain->getIndex()->getHeight(), $hashStop);
    }

    /**
     * @param Buffer|null $hashStop
     * @return BlockLocator
     */
    public function getBlockLocator(Buffer $hashStop = null)
    {
        echo $this->lastBlock->getHeight(). "\n";
        return $this->getLocator($this->lastBlock->getHeight(), $hashStop);
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function checkForHeaders($hash)
    {
        return $this->chain->containsHash($hash);
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function checkForBlock($hash)
    {
        if (!$this->chain->containsHash($hash)) {
            return false;
        }

        $bestBlockHeight = $this->lastBlock->getHeight();
        $blockHeight = $this->chain->getHeightFromHash($hash);
        if ($blockHeight > $bestBlockHeight) {
            return false;
        }

        return true;
    }
}