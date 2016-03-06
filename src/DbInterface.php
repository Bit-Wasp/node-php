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
use BitWasp\Bitcoin\Node\Index\Headers;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Utxo\Utxo;
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
     * @param BufferInterface $blockHash
     * @param BlockInterface $block
     * @return bool
     * @throws \Exception
     */
    public function insertBlock(BufferInterface $blockHash, BlockInterface $block);

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
     * @param int $blockId
     * @return TransactionCollection
     */
    public function fetchBlockTransactions($blockId);

    /**
     * @param BufferInterface $hash
     * @return Block
     */
    public function fetchBlock(BufferInterface $hash);

    /**
     * @param BufferInterface $tipHash
     * @param BufferInterface $txid
     * @return TransactionInterface
     */
    public function getTransaction(BufferInterface $tipHash, BufferInterface $txid);

    /**
     * @param BufferInterface $tipHash
     * @param OutPointInterface[] $outpoints
     * @return Utxo[]
     */
    public function fetchUtxoList(BufferInterface $tipHash, array $outpoints);

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
     * @param BufferInterface $hash
     * @return BlockHeaderInterface[]
     */
    public function fetchNextHeaders(BufferInterface $hash);

    /**
     * @param BufferInterface $hash
     * @param int $numAncestors
     * @return array
     */
    public function findSuperMajorityInfoByHash(BufferInterface $hash, $numAncestors = 1000);
}
