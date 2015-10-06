<?php

namespace BitWasp\Bitcoin\Node\Index;


use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Chain\Difficulty;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Node\BlockIndex;
use BitWasp\Bitcoin\Node\Chain;
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
     * @var Chain[]
     */
    private $tips = [];

    /**
     * @var Chain
     */
    private $activeTip;

    /**
     * @param MySqlDb $db
     * @param EcAdapterInterface $ecAdapter
     * @param Params $params
     */
    public function __construct(MySqlDb $db, EcAdapterInterface $ecAdapter, Params $params)
    {
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

        try {
            $this->tips = $this->db->fetchTips($this);
        } catch (\Exception $e) {
            // This should not occur after inserting the first BlockIndex
            throw $e;
        }

        $this->checkActiveTip();
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
     * @return Chain
     */
    public function getActiveChain()
    {
        return $this->activeTip;
    }

    /**
     * @return \BitWasp\Bitcoin\Node\Chain[]
     */
    public function getChainTips()
    {
        return $this->tips;
    }

    /**
     * @return int
     */
    public function getChainHeight()
    {
        return $this->activeTip->getIndex()->getHeight();
    }

    /**
     * Produce a block locator for a given block height.
     * @param int $height
     * @param bool|true $all
     * @return BlockLocator
     */
    public function getLocator($height, $all = true)
    {
        echo "Produce block locator ($height) \n";
        $step = 1;
        $hashes = [];
        $math = $this->adapter->getMath();
        $headerHash = $this->activeTip->getHashFromHeight($height);

        $h = [];
        while (true) {
            $hashes[] = Buffer::hex($headerHash, 32, $math);
            $h[$height] = $headerHash;
            if ($height == 0) {
                break;
            }

            $height = max($height - $step, 0);
            $headerHash = $this->activeTip->getHashFromHeight($height);
            if (count($hashes) >= 10) {
                $step *= 2;
            }
        }

        if ($all || count($hashes) == 1) {
            $hashStop = new Buffer('', 32, $math);
        } else {
            $hashStop = array_pop($hashes);
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
        return $this->getLocator($this->getChainHeight());
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
     * @param string $hash
     * @return bool
     */
    public function isTip($hash)
    {
        return isset($this->tips[$hash]);
    }

    /**
     * @param array $map
     * @param BlockIndex $index
     * @return Chain
     */
    public function newTip(array $map, BlockIndex $index)
    {
        $this->tips[$index->getHash()] = $tip = new Chain($map, $index, $this, $this->adapter->getMath());
        return $tip;
    }

    /**
     * Adds a header to the index. Will update the current tip,
     * otherwise creates a new tip for others to follow.
     * @param BlockIndex $ancestor
     * @param BlockHeaderInterface $header
     *
    public function addToIndex(BlockIndex $ancestor, BlockHeaderInterface $header)
    {
        $hash = $header->getBlockHash();

        try {
            // Must have the prevBlock
            $hashPrevBlock = $ancestor->getHash();

            // We create a BlockIndex
            $math = $this->adapter->getMath();
            $newHeight = $math->add($ancestor->getHeight(), 1);
            $newWork = $math->add($this->difficulty->getWork($header->getBits()), $ancestor->getWork());
            $newIndex = new BlockIndex($hash, $newHeight, $newWork, $header);

            $this->db->insertIndex($newIndex);

            if ($this->isTip($hashPrevBlock)) {

                $oldTip = $this->tips[$hashPrevBlock];
                $map = $oldTip->getMap();
                $map[] = $hash;
                $newTip = new Chain($newIndex->getHeight(), $newIndex->getWork(), $map, $header, $this, $math);
                //$this->db->updateTip($hashPrevBlock, $oldTip, $hash, $newTip);
                $this->tips[$hash] = $newTip;
                unset($this->tips[$hashPrevBlock]);

            } else {
                echo "ERRRRRRRRR INSERT NEW TIP\n";
                // Create one from an INDEX
                die();
                //$this->tips[] = $tip = new Chain($newIndex->getHeight(), $newIndex->getWork(), $header, $this, $math);
                //$this->db->insertTip($tip);
            }
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
            return;
        }
    }/**/

    /**
     *
     */
    public function checkActiveTip()
    {
        $tips = $this->tips;
        $sort = function (Chain $a, Chain $b) {
            return $this->adapter->getMath()->cmp($a->getIndex()->getWork(), $b->getIndex()->getWork());
        };

        usort($tips, $sort);

        $greatestWork = end($tips);
        $this->activeTip = $greatestWork;
        echo "Setting this one: \n";
        echo " :::: " . $greatestWork->getIndex()->getHash() . " (work: " . $greatestWork->getIndex()->getWork() . ") height - " . $this->activeTip->getIndex()->getHeight() . "\n\n";
    }

    /**
     * @param BlockHeaderInterface $header
     * @return bool
     *
    public function accept(BlockHeaderInterface $header)
    {
        if ($header == $this->genesis) {
            return true;
        }

        $hash = $header->getBlockHash();
        try {
            if ($this->db->haveHeader($hash)) {
                // todo: check for rejected block
                return true;
            }
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
            die();
        }

        if (!$this->check($header)) {
            echo "failed to check\n";
            return false;
        }

        try {
            $prev = $this->db->fetchIndex($header->getPrevBlock());
            $this->addToIndex($prev, $header);
            // todo: check if this block was rejected
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
            echo "previous index not found\n";
            return false;
        }

        return true;
    }/**/

    /**
     * @param BlockHeaderInterface[] $headers
     * @return bool
     */
    public function acceptBatch(array $headers)
    {
        $first = $headers[0];
        foreach ($this->tips as $tTip) {
            $tipHash = $tTip->getIndex()->getHash();
            if ($first->getPrevBlock() == $tipHash) {
                $tip = $tTip;
                break;
            }
        }

        if (!isset($tip)) {
            echo "previous: " . $first->getPrevBlock() . "\n";
            echo "The previous was not a tip\n";
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
                echo "check fail! bail!\n";
                return false;
            }

            // We create a BlockIndex
            $math = $this->adapter->getMath();
            $newHeight = $math->add($prevIndex->getHeight(), 1);
            $newWork = $math->add($this->difficulty->getWork($header->getBits()), $prevIndex->getWork());

            $tip->updateTip(new BlockIndex($hash, $newHeight, $newWork, $header));

            $batch[] = $tip->getIndex();
        }
        echo "\n";

        $this->db->insertIndexBatch($startIndex, $batch);

        unset($this->tips[$startIndex->getHash()]);
        $this->tips[$tip->getIndex()->getHash()] = $tip;

        unset($batch);
        // Starting at $startIndex, do a batch update of the chain

        return true;
    }
}