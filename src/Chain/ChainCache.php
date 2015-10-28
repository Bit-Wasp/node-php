<?php

namespace BitWasp\Bitcoin\Node\Chain;



class ChainCache
{
    /**
     * @var array
     */
    private $hashByHeight = [];

    /**
     * @var array
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
     * @param BlockIndex $index
     */
    public function add(BlockIndex $index)
    {
        if ($index->getHeader()->getPrevBlock() !== $this->getHash($index->getHeight() - 1)) {
            throw new \RuntimeException('ChainCache: New BlockIndex does not refer to last');
        }

        $binary = hex2bin($index->getHash());
        $this->hashByHeight[] = $binary;
        $this->heightByHash[$binary] = $index->getHeight();
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function containsHash($hash)
    {
        return isset($this->heightByHash[hex2bin($hash)]);
    }

    /**
     * @param string $hash
     * @return int
     */
    public function getHeight($hash)
    {
        if ($this->containsHash($hash)) {
            return $this->heightByHash[hex2bin($hash)];
        }

        throw new \RuntimeException('Hash not found');
    }

    /**
     * @param int $height
     * @return string
     */
    public function getHash($height)
    {
        if (!isset($this->hashByHeight[$height])) {
            throw new \RuntimeException('ChainCache: index at this height ('.$height.') not known');
        }

        return bin2hex($this->hashByHeight[$height]);
    }

    /**
     * @param int $endHeight
     * @return ChainCache
     */
    public function subset($endHeight)
    {
        if ($endHeight > count($this->hashByHeight)) {
            throw new \InvalidArgumentException('ChainCache::subset() - end height exceeds size of this cache');
        }

        return new self(array_slice($this->hashByHeight, 0, $endHeight));
    }
}