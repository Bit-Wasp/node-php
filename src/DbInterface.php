<?php

namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Collection\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Node\Index\Headers;
use BitWasp\Buffertools\BufferInterface;

interface DbInterface
{
    /**
     *
     */
    public function stop();

    /**
     * @return bool
     */
    public function wipe();

    /**
     * @return bool
     */
    public function resetBlocksOnly();

    /**
     * @return bool
     */
    public function reset();

    /**
     * Creates the Genesis block index
     * @param BlockHeaderInterface $header
     * @return bool
     */
    public function createIndexGenesis(BlockHeaderInterface $header);

    /**
     * @param BlockIndexInterface $index
     */
    public function createBlockIndexGenesis(BlockIndexInterface $index);

    /**
     * @param BlockInterface $block
     * @return bool
     * @throws \Exception
     */
    public function insertBlockOld(BlockInterface $block);

    /**
     * @param BufferInterface $blockHash
     * @param BlockInterface $block
     * @return bool
     * @throws \Exception
     */
    public function insertBlock(BufferInterface $blockHash, BlockInterface $block);

    /**
     * @param BlockIndexInterface $startIndex
     * @param BlockIndexInterface[] $index
     * @return bool
     * @throws \Exception
     */
    public function insertIndexBatch(BlockIndexInterface $startIndex, array $index);

    /**
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetchIndex(BufferInterface $hash);

    /**
     * @param int $id
     * @return BlockIndexInterface
     */
    public function fetchIndexById($id);

    /**
     * @param BufferInterface $blockHash
     * @return TransactionCollection
     */
    public function fetchBlockTransactions(BufferInterface $blockHash);

    /**
     * @param BufferInterface $hash
     * @return Block
     */
    public function fetchBlock(BufferInterface $hash);

    /**
     * @param Headers $headers
     * @param BufferInterface $hash
     * @return ChainStateInterface
     */
    public function fetchHistoricChain(Headers $headers, BufferInterface $hash);

    /**
     * @param Headers $headers
     * @return ChainStateInterface[]
     */
    public function fetchChainState(Headers $headers);

    /**
     * We use this to help other nodes sync headers. Identify last common
     * hash in our chain
     *
     * @param ChainInterface $activeChain
     * @param BlockLocator $locator
     * @return false|string
     */
    public function findFork(ChainInterface $activeChain, BlockLocator $locator);

    /**
     * Here, we return max 2000 headers following $hash.
     * Useful for helping other nodes sync.
     * @param string $hash
     * @return BlockHeaderInterface[]
     */
    public function fetchNextHeaders($hash);

    /**
     * @param BlockInterface $block
     * @return array
     */
    public function filterUtxoRequest(BlockInterface $block);

    /**
     * @param BlockInterface $block
     * @return UtxoView
     */
    public function fetchUtxoView(BlockInterface $block);

    public function fetchUtxos($required, $bestBlock);

    public function fetchActiveSuperMajority($headerId, array $versions);

    public function findSuperMajorityInfo($headerId, array $versions);

    /**
     * @param BufferInterface $hash
     * @param int[] $versions
     * @return array
     */
    public function findSuperMajorityInfoByHash(BufferInterface $hash, array $versions);
}
