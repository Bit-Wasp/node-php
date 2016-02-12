<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainsInterface;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\Db;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Node\Validation\HeaderCheckInterface;
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
     * Headers constructor.
     * @param Db $db
     * @param Consensus $consensus
     * @param Math $math
     * @param ChainsInterface $chains
     * @param HeaderCheckInterface $headerCheck
     */
    public function __construct(
        Db $db,
        Consensus $consensus,
        Math $math,
        ChainsInterface $chains,
        HeaderCheckInterface $headerCheck
    ) {
        $this->db = $db;
        $this->math = $math;
        $this->chains = $chains;
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
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetch(BufferInterface $hash)
    {
        return $this->db->fetchIndex($hash);
    }

    /**
     * @param BufferInterface $hash
     * @return array
     */
    private function fetchChain(BufferInterface $hash)
    {
        $isTip = $this->chains->isTip($hash);
        if ($isTip instanceof ChainState) {
            return [true, $isTip];
        } else {
            return [false, $this->db->fetchHistoricChain($this, $hash)];
        }
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

        /* @var ChainStateInterface $state */
        list ($isTip, $state) = $this->fetchChain($hash);

        $chain = $state->getChain();
        $startIndex = $chain->getIndex();

        $chain->updateTip(
            $this
                ->headerCheck
                ->check($hash, $header)
                ->checkContextual($state, $header)
                ->makeIndex($startIndex, $hash, $header)
        );

        $index = $chain->getIndex();

        $this->db->insertIndexBatch($startIndex, [$index]);

        if (!$isTip) {
            $this->chains->trackState($state);
        }

        return $index;
    }

    /**
     * @param BlockHeaderInterface[] $headers
     * @param ChainStateInterface|null $state
     * @param BlockIndexInterface|null $prevIndex
     * @return ChainStateInterface
     * @throws \Exception
     */
    public function acceptBatch(array $headers, ChainStateInterface &$state = null, BlockIndexInterface &$prevIndex = null)
    {
        $countHeaders = count($headers);
        $bestPrev = null;
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

        /* @var ChainState $chainState */
        list ($isTip, $chainState) = $this->fetchChain($bestPrev);
        $tip = $chainState->getChain();
        $prevIndex = $tip->getIndex();

        if ($firstUnknown !== null) {
            $batch = [];
            for ($i = $firstUnknown; $i < $countHeaders; $i++) {
                list ($hash, $header) = $headers[$i];

                $index = $this
                    ->headerCheck
                    ->check($hash, $header)
                    ->checkContextual($chainState, $header)
                    ->makeIndex($tip->getIndex(), $hash, $header);

                // This function checks for continuity of headers
                $tip->updateTip($index);

                $batch[] = $tip->getIndex();
            }

            // Do a batch update of the chain
            if (count($batch) > 0) {
                $this->db->insertIndexBatch($prevIndex, $batch);
                unset($batch);
            }

            if (!$isTip) {
                $this->chains->trackState($chainState);
            }
        }

        $prevIndex = $tip->getIndex();
        $state = $chainState;

        return true;
    }
}
