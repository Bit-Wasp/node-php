<?php

namespace BitWasp\Bitcoin\Node\Db;

use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainSegment;
use BitWasp\Bitcoin\Node\Chain\ChainViewInterface;
use BitWasp\Bitcoin\Node\HashStorage;
use BitWasp\Bitcoin\Node\Index\Validation\BlockAcceptData;
use BitWasp\Bitcoin\Node\Index\Validation\BlockData;
use BitWasp\Bitcoin\Node\Index\Validation\HeadersBatch;
use BitWasp\Bitcoin\Serializer\Block\BlockSerializerInterface;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializerInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\BufferInterface;

interface DbInterface
{
    /**
     * @return \PDO
     */
    public function getPdo();
    
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
     * @param array $history
     * @return BlockIndexInterface
     */
    public function findSegmentBestBlock(array $history, $status);
    
    /**
     * @param int $segmentId
     * @return array
     */
    public function loadHashesForSegment($segmentId);

    /**
     * @param int $segment
     * @param int $segmentStart
     * @return int
     */
    public function loadSegmentAncestor($segment, $segmentStart);

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
     * @return TransactionInterface[]
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
     * @param OutPointSerializerInterface $outpointSerializer
     * @param string[] $outpointKeys
     * @return \BitWasp\Bitcoin\Utxo\Utxo[]
     */
    public function fetchUtxoDbList(OutPointSerializerInterface $outpointSerializer, array $outpointKeys);

    /**
     * @return ChainSegment[]
     */
    public function fetchChainSegments();

    /**
     * @param int $blockId
     * @param BlockInterface $block
     * @param HashStorage $hashStorage
     * @return bool
     */
    public function insertBlockTransactions($blockId, BlockInterface $block, HashStorage $hashStorage);

    /**
     * @param OutPointSerializerInterface $serializer
     * @param BlockData $blockData
     */
    public function updateUtxoSet(OutPointSerializerInterface $serializer, BlockData $blockData);

    /**
     * @param HeadersBatch $batch
     * @return bool
     * @throws \Exception
     */
    public function insertHeaderBatch(HeadersBatch $batch);

    /**
     * @param BufferInterface $blockHash
     * @param BufferInterface $block
     * @param BlockAcceptData $acceptData
     * @param int $status
     * @return mixed
     */
    public function insertBlockRaw(BufferInterface $blockHash, BufferInterface $block, BlockAcceptData $acceptData, $status);

    /**
     * @param BufferInterface $hash
     * @param BlockInterface $block
     * @param BlockSerializerInterface $blockSerializer
     * @param int $status
     * @return int
     */
    public function insertBlock(BufferInterface $hash, BlockInterface $block, BlockSerializerInterface $blockSerializer, $status);

    /**
     * @param BlockIndexInterface $index
     * @param $status
     * @return mixed
     */
    public function updateBlockStatus(BlockIndexInterface $index, $status);

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
     * @param ChainViewInterface $view
     * @param int $numAncestors
     * @return array
     */
    public function findSuperMajorityInfoByView(ChainViewInterface $view, $numAncestors = 1000);
    /**
     * @param callable $function
     * @return void
     * @throws \Exception
     */
    public function transaction(callable $function);
}
