<?php

namespace BitWasp\Bitcoin\Node\Index;



use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Node\BlockIndex;
use BitWasp\Bitcoin\Node\Chains;
use BitWasp\Bitcoin\Node\ChainState;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\MySqlDb;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Node\Params;

class Headers
{
    /**
     * @var EcAdapterInterface
     */
    private $adapter;

    /**
     * @var BlockHeaderInterface
     */
    private $genesis;

    /**
     * @var string
     */
    private $genesisHash;

    /**
     * @var ProofOfWork
     */
    private $pow;

    /**
     * @param MySqlDb $db
     * @param EcAdapterInterface $ecAdapter
     * @param Params $params
     * @param ProofOfWork $proofOfWork
     */
    public function __construct(
        MySqlDb $db,
        EcAdapterInterface $ecAdapter,
        Params $params,
        ProofOfWork $proofOfWork
    ) {
        $this->db = $db;
        $this->adapter = $ecAdapter;
        $this->params = $params;
        $this->consensus = new Consensus($this->adapter->getMath(), $this->params);
        $this->pow = $proofOfWork;
        $this->genesis = $params->getGenesisBlock()->getHeader();
        $this->genesisHash = $this->genesis->getBlockHash();
        $this->init();
    }

    /**
     * Initialize the block storage with genesis and chain
     */
    public function init()
    {
        try {
            $this->db->fetchIndex($this->genesisHash);
        } catch (\Exception $e) {
            $idx = new BlockIndex($this->genesisHash, 0, 0, $this->genesis);
            $this->db->insertIndexGenesis($idx);
        }
    }

    /**
     * @return BlockHeaderInterface
     */
    public function genesis()
    {
        return $this->genesis;
    }

    /**
     * @return string
     */
    public function genesisHash()
    {
        return $this->genesisHash;
    }

    /**
     * @param string $hash
     * @return BlockIndex
     */
    public function fetchByHash($hash)
    {
        return $this->db->fetchIndex($hash);
    }

    /**
     * @param BlockHeaderInterface $header
     * @param bool|true $checkPow
     * @return $this
     */
    public function check(BlockHeaderInterface $header, $checkPow = true)
    {
        try {
            if ($checkPow) {
                $this->pow->checkHeader($header);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Headers::check() - failed validating the header');
        }

        return $this;
    }

    /**
     * @param ChainState $state
     * @param BlockHeaderInterface $header
     * @return $this
     */
    public function checkContextual(ChainState $state, BlockHeaderInterface $header)
    {
        $math = $this->adapter->getMath();
        $work = $this->consensus->getWorkRequired($state);
        if ($math->cmp($header->getBits()->getInt(), $work) != 0) {
            throw new \RuntimeException('Headers::CheckContextual(): invalid proof of work : ' . $header->getBits()->getInt() . '? ' . $work);
        }

        // check timestamp
        // reject block version 1 when 95% has upgraded
        // reject block version 2 when 95% has upgraded

        return $this;
    }

    /**
     * @param ChainState $state
     * @param BlockHeaderInterface $header
     * @return BlockIndex
     * @throws \Exception
     */
    public function addToIndex(ChainState $state, BlockHeaderInterface $header)
    {
        $math = $this->adapter->getMath();

        $ancestor = $state->getChain()->getIndex();

        // We create a BlockIndex
        $newIndex = new BlockIndex(
            $header->getBlockHash(),
            $math->add($ancestor->getHeight(), 1),
            $math->add($this->pow->getWork($header->getBits()), $ancestor->getWork()),
            $header
        );

        $this->db->insertIndexBatch($ancestor, [$newIndex]);

        $state->getChain()->updateTip($newIndex);

        return $newIndex;
    }

    /**
     * @param ChainState $state
     * @param BlockHeaderInterface $header
     * @return BlockIndex
     */
    public function accept(ChainState $state, BlockHeaderInterface $header)
    {
        $hash = $header->getBlockHash();
        if ($header == $this->genesis || $state->getChain()->containsHash($hash)) {
            // todo: check for rejected block
            return $this->db->fetchIndex($hash);
        }

        return $this
            ->check($header)
            ->checkContextual($state, $header)
            ->addToIndex($state, $header);
    }

    /**
     * @param ChainState $state
     * @param BlockHeaderInterface[] $headers
     * @return bool
     * @throws \Exception
     */
    public function acceptBatch(ChainState $state, array $headers)
    {
        $math = $this->adapter->getMath();
        $tip = $state->getChain();

        $batch = array();
        $startIndex = $tip->getIndex();

        foreach ($headers as $header) {
            $prevIndex = $tip->getIndex();

            if ($prevIndex->getHash() !== $header->getPrevBlock()) {
                echo "Our tip: " . $prevIndex->getHash() . "\n";
                echo "New block: " . $header->getBlockHash() . "\n";
                echo "points to " . $header->getPrevBlock() . "\n";
                $havePrev = $state->getChain()->getChainCache()->containsHash($header->getPrevBlock());
                $haveBest = $state->getChain()->getChainCache()->containsHash($header->getBlockHash());
                echo "Have prev: " . ($havePrev ? 'yes' : 'no') . "\n";
                echo "Have best: " . ($haveBest ? 'yes' : 'no') . "\n";
                throw new \RuntimeException('Header mismatch, header.prevBlock does not refer to tip');
            }

            $hash = $header->getBlockHash();
            if ($tip->containsHash($hash)) {
                return true;
            }

            $this
                ->check($header)
                ->checkContextual($state, $header);

            // We create a BlockIndex
            $tip->updateTip(new BlockIndex(
                $header->getBlockHash(),
                $math->add($prevIndex->getHeight(), 1),
                $math->add($this->pow->getWork($header->getBits()), $prevIndex->getWork()),
                $header
            ));

            $batch[] = $tip->getIndex();
        }

        // Starting at $startIndex, do a batch update of the chain
        $this->db->insertIndexBatch($startIndex, $batch);
        unset($batch);

        return true;
    }
}