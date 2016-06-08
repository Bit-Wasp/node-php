<?php

namespace BitWasp\Bitcoin\Node\Db;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainSegment;
use BitWasp\Bitcoin\Node\HashStorage;
use BitWasp\Bitcoin\Node\Index\Validation\HeadersBatch;
use BitWasp\Bitcoin\Serializer\Block\BlockSerializerInterface;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
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
     * @return BlockIndexInterface
     */
    public function findSegmentBestBlock(array $history)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->findSegmentBestBlock($history);
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
     * @param BufferInterface $hash
     * @param BlockInterface $block
     * @param BlockSerializerInterface $blockSerializer
     * @return int
     */
    public function insertBlock(BufferInterface $hash, BlockInterface $block, BlockSerializerInterface $blockSerializer)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->insertBlock($hash, $block, $blockSerializer);
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
     * @return \BitWasp\Bitcoin\Collection\Transaction\TransactionCollection
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

    public function fetchUtxoList(BufferInterface $tipHash, array $outpoints)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchUtxoList($tipHash, $outpoints);
    }

    /**
     * @param OutPointSerializer $outpointSerializer
     * @param array $outpoints
     * @return \BitWasp\Bitcoin\Utxo\Utxo[]
     */
    public function fetchUtxoDbList(OutPointSerializer $outpointSerializer, array $outpoints)
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
     * @param OutPointSerializer $serializer
     * @param array $deleteOutPoints
     * @param array $newUtxos
     * @param array $specificDeletes
     */
    public function updateUtxoSet(OutPointSerializer $serializer, array $deleteOutPoints, array $newUtxos, array $specificDeletes = [])
    {
        echo __FUNCTION__ . PHP_EOL;
        $this->db->updateUtxoSet($serializer, $deleteOutPoints, $newUtxos, $specificDeletes);
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
     * @param callable $function
     */
    public function transaction(callable $function)
    {
        echo __FUNCTION__ . PHP_EOL;
        $this->db->transaction($function);
    }
}
