<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Crypto\Hash;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainsInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Chain\Forks;
use BitWasp\Bitcoin\Node\Chain\HeadersBatch;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\Db;
use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Node\HashStorage;
use BitWasp\Bitcoin\Node\Index\Validation\HeaderCheck;
use BitWasp\Bitcoin\Node\Index\Validation\HeaderCheckInterface;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitter;

class Headers extends EventEmitter
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
     * @var ChainsInterface
     */
    private $chains;

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
     * @param DbInterface $db
     * @param EcAdapterInterface $ecAdapter
     * @param ChainsInterface $chains
     * @param Consensus $consensus
     * @param ProofOfWork $proofOfWork
     */
    public function __construct(
        DbInterface $db,
        EcAdapterInterface $ecAdapter,
        ChainsInterface $chains,
        Consensus $consensus,
        ProofOfWork $proofOfWork
    ) {
    
        $this->db = $db;
        $this->math = $ecAdapter->getMath();
        $this->chains = $chains;
        $this->consensus = $consensus;
        $this->proofOfWork = $proofOfWork;
        $this->headerCheck = new HeaderCheck($consensus, $ecAdapter, $proofOfWork);
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
        $hashStorage = new HashStorage();
        foreach ($headers as $i => &$head) {
            if ($this->chains->isKnownHeader($head->getPrevBlock())) {
                $bestPrev = $head->getPrevBlock();
            }

            $hash = Hash::sha256d($head->getBuffer())->flip();
            $hashStorage->attach($head, $hash);
            if ($firstUnknown === null && !$this->chains->isKnownHeader($hash)) {
                $firstUnknown = $i;
            }
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
                $header = $headers[$i];
                $hash = $hashStorage[$header];

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

        $this->emit('headers', [$batch]);

        return $this;
    }
}
