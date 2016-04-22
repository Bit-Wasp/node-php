<?php

namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Chain\HeadersBatch;
use BitWasp\Bitcoin\Node\Index\Headers;
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
    public function createMiniUtxoView(OutPointSerializer $outpointSerializer, array $outpoints)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->createMiniUtxoView($outpointSerializer, $outpoints);
    }
    public function deleteUtxoView()
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->deleteUtxoView();
        // TODO: Implement deleteUtxoView() method.
    }
    public function updateUtxoView(OutPointSerializer $serializer, array $deleteUtxos, array $newUtxos, array $specificDeletes = [])
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->updateUtxoView($serializer, $deleteUtxos, $newUtxos, $specificDeletes);
    }

    public function getPdo()
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->getPdo();
    }

    public function appendUtxoViewKeys(array $cacheHits)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->appendUtxoViewKeys($cacheHits);
    }

    public function stop()
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->stop();
    }

    public function wipe()
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->wipe();
    }

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

    public function createIndexGenesis(BlockHeaderInterface $header)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->createIndexGenesis($header);
    }

    public function fetchIndex(BufferInterface $hash)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchIndex($hash);
    }

    public function fetchIndexById($id)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchIndexById($id);
    }

    public function fetchBlockTransactions($blockId)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchBlockTransactions($blockId);
    }

    public function fetchBlock(BufferInterface $hash)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchBlock($hash);
    }

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

    public function fetchUtxoDbList(OutPointSerializer $outpointSerializer, array $outpoints)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchUtxoDbList($outpointSerializer, $outpoints);
    }

    public function fetchHistoricChain(Headers $headers, BufferInterface $hash)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchHistoricChain($headers, $hash);
    }

    public function fetchChainState(Headers $headers)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchChainState($headers);
    }

    public function insertToBlockIndex(BufferInterface $index)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->insertToBlockIndex($index);
    }

    public function insertBlockTransactions($blockId, BlockInterface $block, HashStorage $hashStorage)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->insertBlockTransactions($blockId, $block, $hashStorage);
    }

    public function updateUtxoSet(OutPointSerializer $serializer, array $deleteOutPoints, array $newUtxos, array $specificDeletes = [])
    {
        echo __FUNCTION__ . PHP_EOL;
        $this->db->updateUtxoSet($serializer, $deleteOutPoints, $newUtxos, $specificDeletes);
    }

    public function findFork(ChainStateInterface $activeChain, BlockLocator $locator)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->findFork($activeChain, $locator);
    }

    public function fetchNextHeaders(BufferInterface $hash)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->fetchNextHeaders($hash);
    }

    public function findSuperMajorityInfoByHash(BufferInterface $hash, $numAncestors = 1000)
    {
        echo __FUNCTION__ . PHP_EOL;
        return $this->db->findSuperMajorityInfoByHash($hash, $numAncestors);
    }

    public function transaction(callable $function)
    {
        echo __FUNCTION__ . PHP_EOL;
        $this->db->transaction($function);
    }
}
