<?php

namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Collection\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Chain\HeadersBatch;
use BitWasp\Bitcoin\Node\Index\Headers;
use BitWasp\Bitcoin\Serializer\Block\BlockSerializerInterface;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\BufferInterface;

interface DbInterface
{
    /**
     * @return \PDO
     */
    public function getPdo();

    public function createMiniUtxoView(OutPointSerializer $outpointSerializer, array $outpoints);
    public function updateUtxoView(OutPointSerializer $serializer, array $deleteUtxos, array $newUtxos, array $specificDeletes = []);
    public function deleteUtxoView();
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
     * @param OutPointSerializer $outpointSerializer
     * @param OutPointInterface[] $outpoints
     * @return \BitWasp\Bitcoin\Utxo\Utxo[]
     */
    public function fetchUtxoDbList(OutPointSerializer $outpointSerializer, array $outpoints);

    /**
     * @param string[] $cacheHits
     * @return mixed
     */
    public function appendUtxoViewKeys(array $cacheHits);

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
     * @param BufferInterface $index
     * @return int
     */
    public function insertToBlockIndex(BufferInterface $index);

    /**
     * @param int $blockId
     * @param BlockInterface $block
     * @param HashStorage $hashStorage
     * @return bool
     */
    public function insertBlockTransactions($blockId, BlockInterface $block, HashStorage $hashStorage);

    /**
     * @param OutPointSerializer $serializer
     * @param array $deleteOutPoints
     * @param array $newUtxos
     * @param array $specificDeletes
     * @return void
     */
    public function updateUtxoSet(OutPointSerializer $serializer, array $deleteOutPoints, array $newUtxos, array $specificDeletes = []);

    /**
     * @param HeadersBatch $batch
     * @return bool
     * @throws \Exception
     */
    public function insertHeaderBatch(HeadersBatch $batch);

    /**
     * @param BufferInterface $hash
     * @param BlockInterface $block
     * @param BlockSerializerInterface $blockSerializer
     * @return int
     */
    public function insertBlock(BufferInterface $hash, BlockInterface $block, BlockSerializerInterface $blockSerializer);

    /**
     * We use this to help other nodes sync headers. Identify last common
     * hash in our chain
     *
     * @param ChainStateInterface $activeChain
     * @param BlockLocator $locator
     * @return BufferInterface
     */
    public function findFork(ChainStateInterface $activeChain, BlockLocator $locator);

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

    /**
     * @param callable $function
     * @return void
     * @throws \Exception
     */
    public function transaction(callable $function);
}
