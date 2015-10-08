<?php

namespace BitWasp\Bitcoin\Node\Index;


use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Chain\Difficulty;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Node\BlockIndex;
use BitWasp\Bitcoin\Node\Chain;
use BitWasp\Bitcoin\Node\Chains;
use BitWasp\Bitcoin\Node\MySqlDb;
use BitWasp\Bitcoin\Node\Params;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Buffertools\Buffer;

class Headers
{
    /**
     * @var EcAdapterInterface
     */
    private $adapter;

    /**
     * @var Difficulty
     */
    private $difficulty;

    /**
     * @var ProofOfWork
     */
    private $pow;

    /**
     * @var BlockHeaderInterface
     */
    private $genesis;

    /**
     * @var string
     */
    private $genesisHash;

    /**
     * @var Chains
     */
    private $chains;

    /**
     * @param MySqlDb $db
     * @param EcAdapterInterface $ecAdapter
     * @param Params $params
     * @param Chains $chains
     * @throws \Exception
     */
    public function __construct(MySqlDb $db, EcAdapterInterface $ecAdapter, Params $params, Chains $chains)
    {
        $this->chains = $chains;
        $this->db = $db;
        $this->adapter = $ecAdapter;
        $this->params = $params;
        $this->genesis = $params->getGenesisBlock()->getHeader();
        $this->genesisHash = $this->genesis->getBlockHash();
        $this->init();
        $this->difficulty = new Difficulty($ecAdapter->getMath(), $params->getLowestBits());
        $this->pow = new ProofOfWork($ecAdapter->getMath(), $this->difficulty, '');
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
     * Produce a block locator for a given block height.
     * @param int $height
     * @param Buffer|null $final
     * @return BlockLocator
     */
    public function getLocator($height, Buffer $final = null)
    {
        echo "Produce GETHEADERS locator ($height) \n";
        $step = 1;
        $hashes = [];
        $math = $this->adapter->getMath();
        $tip = $this->chains->best()->getChain();
        $headerHash = $tip->getHashFromHeight($height);

        $h = [];
        while (true) {
            $hashes[] = Buffer::hex($headerHash, 32, $math);
            $h[$height] = $headerHash;
            if ($height == 0) {
                break;
            }

            $height = max($height - $step, 0);
            $headerHash = $tip->getHashFromHeight($height);
            if (count($hashes) >= 10) {
                $step *= 2;
            }
        }

        if (is_null($final)) {
            $hashStop = new Buffer('', 32, $math);
        } else {
            $hashStop = $final;
        }

        return new BlockLocator(
            $hashes,
            $hashStop
        );
    }

    /**
     * Produce a block locator given the current view of the chain
     * @return BlockLocator
     */
    public function getLocatorCurrent()
    {
        return $this->getLocator($this->chains->best()->getChainIndex()->getHeight());
    }

    /**
     * @param string $hash
     * @return BlockHeaderInterface
     */
    public function fetchByHash($hash)
    {
        return $this->db->fetchIndex($hash);
    }

    /**
     * @param BlockHeaderInterface $header
     * @param bool|true $checkPow
     * @return bool
     * @throws \Exception
     */
    public function check(BlockHeaderInterface $header, $checkPow = true)
    {
        if ($checkPow && !$this->pow->checkHeader($header)) {
            return false;
        }

        // todo: check timestamp

        return true;
    }

    /**
     * Adds a header to the index. Will update the current tip,
     * otherwise creates a new tip for others to follow.
     *
     * @param BlockIndex $ancestor
     * @param BlockHeaderInterface $header
     * @return BlockIndex
     * @throws \Exception
     */
    public function addToIndex(BlockHeaderInterface $header)
    {
        $hash = $header->getBlockHash();

        try {
            $tip = $this->chains->findTipForNext($header);
            $ancestor = $tip->getIndex();

            // We create a BlockIndex
            $math = $this->adapter->getMath();
            $newHeight = $math->add($ancestor->getHeight(), 1);
            $newWork = $math->add($this->difficulty->getWork($header->getBits()), $ancestor->getWork());
            $newIndex = new BlockIndex($hash, $newHeight, $newWork, $header);

            $this->db->insertIndexBatch($ancestor, [$newIndex]);

            $tip->updateTip($newIndex);
            return $newIndex;

        } catch (\Exception $e) {
            if ($e instanceof \PDOException) {
                throw $e;
            }

            throw new \RuntimeException('New header did not elongate tip - new fork - have not implemented this yet');
        }
    }/**/

    /**
     * @param BlockHeaderInterface $header
     * @return false|BlockIndex
     */
    public function accept(BlockHeaderInterface $header)
    {
        $hash = $header->getBlockHash();
        if ($header == $this->genesis || $this->db->haveHeader($hash)) {
            // todo: check for rejected block
            return $this->db->fetchIndex($hash);
        }

        if (!$this->check($header)) {
            echo "failed to check\n";
            throw new \RuntimeException('Header invalid');
        }

        echo "Headers: addToIndex()\n";
        return $this->addToIndex($header);

    }/**/

    /**
     * @param BlockHeaderInterface[] $headers
     * @return bool
     */
    public function acceptBatch(array $headers)
    {
        $first = $headers[0];
        try {
            $tip = $this->chains->findTipForNext($first);
        } catch (\Exception $e) {
            return false;
        }

        $batch = array();
        $startIndex = $tip->getIndex();

        foreach ($headers as $header) {
            $prevIndex = $tip->getIndex();

            /** @var \BitWasp\Bitcoin\Block\BlockHeaderInterface $last */
            if ($prevIndex->getHash() !== $header->getPrevBlock()) {
                echo "mismatch\n";
                return false;
            }

            $hash = $header->getBlockHash();
            if ($tip->containsHash($hash)) {
                return true;
            }

            if (!$this->check($header)) {
                return false;
            }

            // We create a BlockIndex
            $math = $this->adapter->getMath();
            $newHeight = $math->add($prevIndex->getHeight(), 1);
            $newWork = $math->add($this->difficulty->getWork($header->getBits()), $prevIndex->getWork());

            $tip->updateTip(new BlockIndex($hash, $newHeight, $newWork, $header));

            $batch[] = $tip->getIndex();
        }

        // Starting at $startIndex, do a batch update of the chain
        $this->db->insertIndexBatch($startIndex, $batch);
        unset($batch);

        $this->chains->checkTips();

        return true;
    }
}