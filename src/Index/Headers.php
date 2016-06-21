<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Crypto\Hash;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainContainer;
use BitWasp\Bitcoin\Node\Chain\ChainsInterface;
use BitWasp\Bitcoin\Node\Chain\ChainViewInterface;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\Db\DbInterface;
use BitWasp\Bitcoin\Node\HashStorage;
use BitWasp\Bitcoin\Node\Index\Validation\Forks;
use BitWasp\Bitcoin\Node\Index\Validation\HeaderCheck;
use BitWasp\Bitcoin\Node\Index\Validation\HeaderCheckInterface;
use BitWasp\Bitcoin\Node\Index\Validation\HeadersBatch;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitter;

class Headers extends EventEmitter
{
    /**
     * @var Consensus
     */
    private $consensus;

    /**
     * @var DbInterface
     */
    private $db;

    /**
     * @var ChainContainer
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
    public function accept(BufferInterface $hash, BlockHeaderInterface $header, $haveBlock = false)
    {
        $isTip = $this->chains->isTip($hash);
        if ($isTip instanceof ChainViewInterface) {
            return $isTip->getIndex();
        }
        
        if ($haveBlock) {
            $hasBlockTip = $this->chains->hasBlockTip($header);
            if ($hasBlockTip instanceof BlockIndexInterface) {
                return $this->getNextIndex($hash, $hasBlockTip, $header);
            }
        }

        if ($this->chains->isKnownHeader($hash)) {
            return $this->db->fetchIndex($hash);
        }

        $batch = $this->prepareBatch([$header]);
        $index = $batch->getIndices()[0];
        $this->applyBatch($batch);

        return $index;
    }

    /**
     * @param BufferInterface $hash
     * @param BlockIndexInterface $prevIndex
     * @param BlockHeaderInterface $header
     * @return BlockIndex
     */
    public function getNextIndex(BufferInterface $hash, BlockIndexInterface $prevIndex, BlockHeaderInterface $header)
    {
        return new BlockIndex(
            $hash,
            $prevIndex->getHeight() + 1,
            $this->math->toString($this->math->add($this->proofOfWork->getWork($header->getBits()), gmp_init($prevIndex->getWork()))),
            $header
        );
    }

    /**
     * @param BlockHeaderInterface[] $headers
     * @return HeadersBatch
     */
    public function prepareBatch(array $headers)
    {
        $countHeaders = count($headers);
        if (0 === $countHeaders) {
            return new HeadersBatch($this->chains->best($this->math), []);
        }

        $bestPrev = null;
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

        $view = $this->chains->isTip($bestPrev);
        if ($view === false) {
            throw new \RuntimeException('Headers::accept(): Unhandled fork');
        }

        $prevIndex = $view->getIndex();
        $access = $this->chains->access($view);

        $batch = [];
        if ($firstUnknown !== null) {
            $versionInfo = $this->db->findSuperMajorityInfoByView($view);

            $forks = new Forks($this->consensus->getParams(), $prevIndex, $versionInfo);

            for ($i = $firstUnknown; $i < $countHeaders; $i++) {
                /**
                 * @var BufferInterface $hash
                 * @var BlockHeaderInterface $header
                 */
                $header = $headers[$i];
                $hash = $hashStorage[$header];
                $this->headerCheck->check($hash, $header);

                $index = $this->getNextIndex($hash, $prevIndex, $header);
                
                $forks->next($index);
                $this->headerCheck->checkContextual($access, $index, $prevIndex, $forks);

                $batch[] = $index;
                $prevIndex = $index;
            }
        }

        return new HeadersBatch($view, $batch);
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
            $tip->updateTip($index);
        }

        $this->emit('tip', [$batch]);

        return $this;
    }
}
