<?php

namespace BitWasp\Bitcoin\Node\Db;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainSegment;
use BitWasp\Bitcoin\Node\HashStorage;
use BitWasp\Bitcoin\Node\Index\Validation\BlockAcceptData;
use BitWasp\Bitcoin\Node\Index\Validation\BlockData;
use BitWasp\Bitcoin\Node\Index\Validation\HeadersBatch;
use BitWasp\Bitcoin\Serializer\Block\BlockSerializerInterface;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializerInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\BufferInterface;

class DebugDb implements DbInterface
{
    /**
     * @var DbInterface
     */
    private $db;

    /**
     * DebugDb constructor.
     * @param DbInterface $db
     */
    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @return \PDO
     */
    public function getPdo()
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->getPdo();
    }

    /**
     * @param ChainSegment[] $history
     * @param int $status
     * @return BlockIndexInterface
     */
    public function findSegmentBestBlock(array $history, $status)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->findSegmentBestBlock($history, $status);
    }

    /**
     *
     */
    public function stop()
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->stop();
    }

    /**
     * @return bool
     */
    public function wipe()
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->wipe();
    }

    /**
     * @param BlockIndexInterface $index
     * @param int $status
     * @return int
     */
    public function updateBlockStatus(BlockIndexInterface $index, $status)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->updateBlockStatus($index, $status);
    }

    /**
     * @param BufferInterface $blockHash
     * @param BufferInterface $block
     * @param BlockAcceptData $acceptData
     * @param int $status
     * @return mixed
     */
    public function insertBlockRaw(BufferInterface $blockHash, BufferInterface $block, BlockAcceptData $acceptData, $status)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->insertBlockRaw($blockHash, $block, $acceptData, $status);
    }

    /**
     * @param BufferInterface $hash
     * @param BlockInterface $block
     * @param BlockSerializerInterface $blockSerializer
     * @param int $status
     * @return int
     */
    public function insertBlock(BufferInterface $hash, BlockInterface $block, BlockSerializerInterface $blockSerializer, $status)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->insertBlock($hash, $block, $blockSerializer, $status);
    }

    /**
     * @param HeadersBatch $batch
     * @return bool
     * @throws \Exception
     */
    public function insertHeaderBatch(HeadersBatch $batch)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->insertHeaderBatch($batch);
    }

    /**
     * @return bool
     */
    public function resetBlocksOnly()
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->resetBlocksOnly();
    }

    public function reset()
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->reset();
    }

    /**
     * @return Chain\ChainSegment[]
     */
    public function fetchChainSegments()
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchChainSegments();
    }

    /**
     * @param int $segmentId
     * @return array
     */
    public function loadHashesForSegment($segmentId)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->loadHashesForSegment($segmentId);
    }

    /**
     * @param int $segment
     * @param int $segmentStart
     * @return int
     */
    public function loadSegmentAncestor($segment, $segmentStart)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->loadSegmentAncestor($segment, $segmentStart);
    }

    /**
     * @param BlockHeaderInterface $header
     * @return bool
     */
    public function createIndexGenesis(BlockHeaderInterface $header)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->createIndexGenesis($header);
    }

    /**
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetchIndex(BufferInterface $hash)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchIndex($hash);
    }

    /**
     * @param int $id
     * @return BlockIndexInterface
     */
    public function fetchIndexById($id)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchIndexById($id);
    }

    /**
     * @param int $blockId
     * @return TransactionInterface[]
     */
    public function fetchBlockTransactions($blockId)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchBlockTransactions($blockId);
    }

    /**
     * @param BufferInterface $hash
     * @return \BitWasp\Bitcoin\Block\Block
     */
    public function fetchBlock(BufferInterface $hash)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchBlock($hash);
    }

    /**
     * @param BufferInterface $tipHash
     * @param BufferInterface $txid
     * @return \BitWasp\Bitcoin\Transaction\TransactionInterface
     */
    public function getTransaction(BufferInterface $tipHash, BufferInterface $txid)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->getTransaction($tipHash, $txid);
    }

    /**
     * @param OutPointSerializerInterface $outpointSerializer
     * @param array $outpoints
     * @return \BitWasp\Bitcoin\Utxo\Utxo[]
     */
    public function fetchUtxoDbList(OutPointSerializerInterface $outpointSerializer, array $outpoints)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchUtxoDbList($outpointSerializer, $outpoints);
    }

    /**
     * @param int $blockId
     * @param BlockInterface $block
     * @param HashStorage $hashStorage
     * @return bool
     */
    public function insertBlockTransactions($blockId, BlockInterface $block, HashStorage $hashStorage)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->insertBlockTransactions($blockId, $block, $hashStorage);
    }

    /**
     * @param OutPointSerializerInterface $serializer
     * @param BlockData $blockData
     */
    public function updateUtxoSet(OutPointSerializerInterface $serializer, BlockData $blockData)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->updateUtxoSet($serializer, $blockData);
    }

    /**
     * @param BufferInterface $hash
     * @return \BitWasp\Bitcoin\Block\BlockHeaderInterface[]
     */
    public function fetchNextHeaders(BufferInterface $hash)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchNextHeaders($hash);
    }

    /**
     * @param BufferInterface $hash
     * @param int $numAncestors
     * @return array
     */
    public function findSuperMajorityInfoByHash(BufferInterface $hash, $numAncestors = 1000)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->findSuperMajorityInfoByHash($hash, $numAncestors);
    }

    /**
     * @param Chain\ChainViewInterface $view
     * @param int $numAncestors
     * @return array
     */
    public function findSuperMajorityInfoByView(Chain\ChainViewInterface $view, $numAncestors = 1000)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->findSuperMajorityInfoByView($view, $numAncestors);
    }

    /**
     * @param callable $function
     */
    public function transaction(callable $function)
    {
        echo __FUNCTION__ . PHP_EOL;
        $this->db->transaction($function);
    }
}
