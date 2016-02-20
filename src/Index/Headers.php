<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainsInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Chain\Forks;
use BitWasp\Bitcoin\Node\Chain\HeadersBatch;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\Db;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Node\Validation\HeaderCheckInterface;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;

class Headers
{
    /**
     * @var Consensus
     */
    private $consensus;

    /**
     * @var Db
     */
    private $db;

    /**
     * @var Math
     */
    private $math;

    /**
     * @var HeaderCheckInterface
     */
    private $headerCheck;

    /**
     * @var ProofOfWork
     */
    private $proofOfWork;

    /**
     * Headers constructor.
     * @param Db $db
     * @param Consensus $consensus
     * @param Math $math
     * @param ChainsInterface $chains
     * @param HeaderCheckInterface $headerCheck
     * @param ProofOfWork $proofOfWork
     */
    public function __construct(
        Db $db,
        Consensus $consensus,
        Math $math,
        ChainsInterface $chains,
        ProofOfWork $proofOfWork,
        HeaderCheckInterface $headerCheck
    ) {
        $this->db = $db;
        $this->math = $math;
        $this->chains = $chains;
        $this->consensus = $consensus;
        $this->headerCheck = $headerCheck;
        $this->proofOfWork = $proofOfWork;
    }

    /**
     * Initialize the block storage with genesis and chain
     * @param BlockHeaderInterface $header
     */
    public function init(BlockHeaderInterface $header)
    {
        $hash = $header->getHash();

        try {
            $this->db->fetchIndex($hash);
        } catch (\Exception $e) {
            $this->db->createIndexGenesis($header);
        }
    }

    /**
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetch(BufferInterface $hash)
    {
        return $this->db->fetchIndex($hash);
    }

    /**
     * @param BufferInterface $hash
     * @param BlockHeaderInterface $header
     * @return BlockIndexInterface
     * @throws \Exception
     */
    public function accept(BufferInterface $hash, BlockHeaderInterface $header)
    {
        if ($this->chains->isKnownHeader($hash)) {
            // todo: check for rejected block
            return $this->db->fetchIndex($hash);
        }

        $batch = $this->prepareBatch([$header]);
        $index = $batch->getIndices()[0];
        $this->applyBatch($batch);

        return $index;
    }

    /**
     * @param BlockHeaderInterface[] $headers
     * @return HeadersBatch
     */
    public function prepareBatch(array $headers)
    {
        $countHeaders = count($headers);
        $bestPrev = new Buffer();
        $firstUnknown = null;
        foreach ($headers as $i => &$head) {
            if ($this->chains->isKnownHeader($head->getPrevBlock())) {
                $bestPrev = $head->getPrevBlock();
            }

            $hash = $head->getHash();
            if ($firstUnknown === null && !$this->chains->isKnownHeader($hash)) {
                $firstUnknown = $i;
            }

            $head = [$hash, $head];
        }

        if (!$bestPrev instanceof BufferInterface) {
            throw new \RuntimeException('Headers::accept(): Unknown start header');
        }

        $chainState = $this->chains->isTip($bestPrev);
        if ($chainState === false) {
            $chainState = $this->db->fetchHistoricChain($this, $bestPrev);
            $this->chains->trackState($chainState);
        }

        /* @var ChainStateInterface $chainState */
        $chain = $chainState->getChain();
        $prevIndex = $chain->getIndex();

        $batch = [];
        if ($firstUnknown !== null) {
            $versionInfo = $this->db->findSuperMajorityInfoByHash($prevIndex->getHash());
            $forks = new Forks($this->consensus->getParams(), $prevIndex, $versionInfo);

            for ($i = $firstUnknown; $i < $countHeaders; $i++) {
                /**
                 * @var BufferInterface $hash
                 * @var BlockHeaderInterface $header
                 */
                list ($hash, $header) = $headers[$i];

                $this->headerCheck->check($hash, $header);

                $index = new BlockIndex(
                    $hash,
                    $this->math->add($prevIndex->getHeight(), 1),
                    $this->math->add($this->proofOfWork->getWork($header->getBits()), $prevIndex->getWork()),
                    $header
                );

                $forks->next($index);
                $this->headerCheck->checkContextual($chain, $index, $prevIndex, $forks);

                $batch[] = $index;
                $prevIndex = $index;
            }
        }

        return new HeadersBatch($chainState, $batch);
    }

    /**
     * @param HeadersBatch $batch
     * @return $this
     * @throws \Exception
     */
    public function applyBatch(HeadersBatch $batch)
    {
        $indices = $batch->getIndices();
        if (count($indices) === 0) {
            return $this;
        }

        $this->db->insertHeaderBatch($batch);

        $tip = $batch->getTip();
        foreach ($batch->getIndices() as $index) {
            $tip->getChain()->updateTip($index);
        }

        return $this;
    }
}
