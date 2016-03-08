<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;

class ChainCache implements ChainCacheInterface
{
    /**
     * @var string[]
     */
    private $hashByHeight = [];

    /**
     * @var int[]
     */
    private $heightByHash = [];

    /**
     * Must be initialized with a list of hashes in binary representation
     * @param array $hashes
     */
    public function __construct(array $hashes)
    {
        $this->hashByHeight = $hashes;
        $this->heightByHash = array_flip($hashes);
    }

    /**
     * @param BufferInterface $hash
     * @return bool
     */
    public function containsHash(BufferInterface $hash)
    {
        return array_key_exists($hash->getBinary(), $this->heightByHash);
    }

    /**
     * @param BufferInterface $hash
     * @return int
     */
    public function getHeight(BufferInterface $hash)
    {
        if ($this->containsHash($hash)) {
            return $this->heightByHash[$hash->getBinary()];
        }

        throw new \RuntimeException('Hash not found');
    }

    /**
     * @param int $height
     * @throws \RuntimeException
     * @return BufferInterface
     */
    public function getHash($height)
    {
        if (!array_key_exists($height, $this->hashByHeight)) {
            throw new \RuntimeException('ChainCache: index at this height (' . $height . ') not known');
        }

        return new Buffer($this->hashByHeight[$height], 32);
    }

    /**
     * @param BlockIndexInterface $index
     */
    public function add(BlockIndexInterface $index)
    {
        if ($index->getHeader()->getPrevBlock() != $this->getHash($index->getHeight() - 1)) {
            throw new \RuntimeException('ChainCache: New BlockIndex does not refer to last');
        }

        $binary = $index->getHash()->getBinary();
        $this->hashByHeight[] = $binary;
        $this->heightByHash[$binary] = $index->getHeight();
    }

    /**
     * @param int $endHeight
     * @return ChainCacheInterface
     */
    public function subset($endHeight)
    {
        if ($endHeight > count($this->hashByHeight)) {
            throw new \InvalidArgumentException('ChainCache::subset() - end height exceeds size of this cache');
        }

        return new self(array_slice($this->hashByHeight, 0, $endHeight));
    }
}
