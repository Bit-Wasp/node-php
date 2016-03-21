<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Index\Headers;
use BitWasp\Bitcoin\Node\Index\Transactions;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitter;

class ChainState extends EventEmitter implements ChainStateInterface
{

    /**
     * @var Math
     */
    private $math;

    /**
     * @var Headers
     */
    private $headers;

    /**
     * @var BlockIndexInterface
     */
    private $index;

    /**
     * @var ChainCacheInterface
     */
    private $chainCache;

    /**
     * @var BlockIndexInterface
     */
    private $lastBlock;

    /**
     * @var GuidedChainCache
     */
    private $lastBlockCache;

    /**
     * ChainState constructor.
     * @param array $map
     * @param BlockIndexInterface $index
     * @param Headers $headers
     * @param Math $math
     * @param BlockIndexInterface $lastBlock
     */
    public function __construct(array $map, BlockIndexInterface $index, Headers $headers, Math $math, BlockIndexInterface $lastBlock)
    {
        $this->math = $math;
        $this->index = $index;
        $this->headers = $headers;
        $this->chainCache = new ChainCache($map);
        $this->lastBlockCache = new GuidedChainCache($this->chainCache, $lastBlock->getHeight());
        $this->lastBlock = $lastBlock;
    }


    /**
     * @param BufferInterface $hash
     * @return bool
     */
    public function containsHash(BufferInterface $hash)
    {
        return $this->chainCache->containsHash($hash);
    }

    /**
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetchIndex(BufferInterface $hash)
    {
        if (!$this->chainCache->containsHash($hash)) {
            throw new \RuntimeException('Index by this hash not known');
        }

        return $this->headers->fetch($hash);
    }

    /**
     * @param int $height
     * @return BlockIndexInterface
     */
    public function fetchAncestor($height)
    {
        return $this->fetchIndex($this->getHashFromHeight($height));
    }

    /**
     * @return ChainCacheInterface
     */
    public function getChainCache()
    {
        return $this->chainCache;
    }

    /**
     * @param BufferInterface $txid
     * @return \BitWasp\Bitcoin\Transaction\Transaction
     */
    public function fetchTransaction(Transactions $txIndex, BufferInterface $txid)
    {
        return $txIndex->fetch($this->getIndex()->getHash(), $txid);
    }

    /**
     * @param BlockIndexInterface $index
     */
    public function updateTip(BlockIndexInterface $index)
    {
        if (!$this->index->isNext($index)) {
            throw new \InvalidArgumentException('Provided Index does not elongate this Chain');
        }

        $this->chainCache->add($index);
        $this->index = $index;
        $this->emit('tip', [$index]);
    }

    /**
     * @param BlockIndexInterface $index
     */
    public function updateLastBlock(BlockIndexInterface $index)
    {
        $this->lastBlockCache->add($index);
        $this->lastBlock = $index;
    }

    /**
     * @param BufferInterface $hash
     * @return int
     */
    public function getHeightFromHash(BufferInterface $hash)
    {
        return $this->chainCache->getHeight($hash);
    }

    /**
     * @param int $height
     * @return BufferInterface
     */
    public function getHashFromHeight($height)
    {
        return $this->chainCache->getHash($height);
    }
    
    /**
     * @return BlockIndexInterface
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @return BlockIndexInterface
     */
    public function getLastBlock()
    {
        return $this->lastBlock;
    }

    /**
     * @return ChainCacheInterface
     */
    public function bestBlocksCache()
    {
        return $this->lastBlockCache;
    }

    /**
     * @return int|string
     */
    public function blocksLeftToSync()
    {
        return ($this->index->getHeight() - $this->lastBlock->getHeight());
    }

    /**
     * @return bool
     */
    public function isSyncing()
    {
        return (($this->blocksLeftToSync() === 0) === false);
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
        return $this->getLocator($this->index->getHeight(), $hashStop);
    }

    /**
     * @param BufferInterface|null $hashStop
     * @return BlockLocator
     */
    public function getBlockLocator(BufferInterface $hashStop = null)
    {
        return $this->getLocator($this->lastBlock->getHeight(), $hashStop);
    }
}
