<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\Db;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Node\Validation\HeaderCheckInterface;
use BitWasp\Buffertools\Buffer;

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
     * @param Db $db
     * @param Consensus $consensus
     * @param Math $math
     * @param HeaderCheckInterface $headerCheck
     */
    public function __construct(
        Db $db,
        Consensus $consensus,
        Math $math,
        HeaderCheckInterface $headerCheck
    ) {
        $this->db = $db;
        $this->math = $math;
        $this->consensus = $consensus;
        $this->headerCheck = $headerCheck;
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
     * @param Buffer $hash
     * @return BlockIndex
     */
    public function fetch(Buffer $hash)
    {
        return $this->db->fetchIndex($hash);
    }

    /**
     * @param ChainState $state
     * @param BlockHeaderInterface $header
     * @return BlockIndex
     */
    public function accept(ChainState $state, BlockHeaderInterface $header)
    {
        $hash = $header->getHash();
        if ($state->getChain()->containsHash($hash)) {
            // todo: check for rejected block
            return $this->db->fetchIndex($hash);
        }

        $prevIndex = $state->getChain()->getIndex();
        $index = $this->headerCheck
            ->check($header)
            ->checkContextual($state, $header)
            ->makeIndex($prevIndex, $header);

        $this->db->insertIndexBatch($prevIndex, [$index]);

        $state->getChain()->updateTip($index);

        return $index;
    }

    /**
     * @param ChainState $state
     * @param BlockHeaderInterface[] $headers
     * @return bool
     * @throws \Exception
     */
    public function acceptBatch(ChainState $state, array $headers)
    {
        $tip = $state->getChain();

        $batch = array();
        $startIndex = $tip->getIndex();

        foreach ($headers as $header) {
            if ($tip->containsHash($header->getHash())) {
                continue;
            }

            $prevIndex = $tip->getIndex();
            if ($prevIndex->getHash() != $header->getPrevBlock()) {
                throw new \RuntimeException('Header mismatch, header.prevBlock does not refer to tip');
            }

            $index = $this
                ->headerCheck
                ->check($header)
                ->checkContextual($state, $header)
                ->makeIndex($prevIndex, $header);

            $tip->updateTip($index);

            $batch[] = $tip->getIndex();
        }

        // Do a batch update of the chain
        if (count($batch) > 0) {
            $this->db->insertIndexBatch($startIndex, $batch);
            unset($batch);
        }

        return true;
    }
}
