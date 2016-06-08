<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Node\Db\DbInterface;
use BitWasp\Buffertools\BufferInterface;
use Evenement\EventEmitter;

class ChainContainer extends EventEmitter implements ChainsInterface
{
    /**
     * @var DbInterface
     */
    private $db;

    /**
     * Map of segId => (segment)
     * @var ChainSegment[]
     */
    private $segments = [];

    /**
     * @var \SplObjectStorage
     */
    private $segmentBlock;

    /**
     * Map of hash => height
     *
     * @var array
     */
    private $hashStorage = [];

    /**
     * Map of thisSeg => prevSeg
     *
     * @var array
     */
    private $chainLink = [];

    /**
     * @var ParamsInterface
     */
    private $params;

    /**
     * @var BlockIndexInterface
     */
    private $genesis;

    /**
     * ChainContainer constructor.
     * @param ParamsInterface $params
     * @param array $segments
     */
    public function __construct(ParamsInterface $params, array $segments = [])
    {
        $this->params = $params;
        $this->segmentBlock = new \SplObjectStorage();
        foreach ($segments as $segment) {
            $this->addSegment($segment);
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->segments);
    }

    /**
     * @param DbInterface $db
     * @throws \Exception
     */
    public function initialize(DbInterface $db)
    {
        $this->db = $db;
        $this->genesis = $db->fetchIndex($this->params->getGenesisBlockHeader()->getHash());

        foreach ($this->segments as $segment) {
            $this->processSegment($db, $segment);
        }
    }

    /**
     * @param DbInterface $db
     * @param ChainSegment $segment
     * @throws \Exception
     */
    private function processSegment(DbInterface $db, ChainSegment $segment)
    {
        $id = $segment->getId();
        if (isset($this->segmentBlock[$segment])) {
            throw new \Exception('Already processed this segment');
        }

        $segCount = $segment->getLast()->getHeight() - $segment->getStart() + 1;
        $hashes = $db->loadHashesForSegment($id);
        if (count($hashes) !== $segCount) {
            throw new \Exception('Not enough hashes found for segment');
        }

        $this->hashStorage[$id] = [];
        foreach ($hashes as $row) {
            $this->hashStorage[$id][$row['hash']] = $row['height'];
        }

        if ($segment->getStart() != 0) {
            $ancestor = $db->loadSegmentAncestor($id, $segment->getStart());
            if (!isset($this->segments[$ancestor])) {
                throw new \RuntimeException('Failed to link segment');
            }

            $this->chainLink[$id] = $ancestor;
        }

        $this->segmentBlock[$segment] = $this->db->findSegmentBestBlock($this->getHistory($segment));
    }

    /**
     * @param ChainSegment $segment
     */
    public function addSegment(ChainSegment $segment)
    {
        $this->segments[$segment->getId()] = $segment;
    }

    /**
     * @param ChainSegment $segment
     * @param BlockIndexInterface $index
     */
    public function updateSegment(ChainSegment $segment, BlockIndexInterface $index)
    {
        $segment->next($index);
        $this->hashStorage[$segment->getId()][$index->getHash()->getBinary()] = $index->getHeight();
    }

    /**
     * @param ChainSegment $segment
     * @param BlockIndexInterface $index
     */
    public function updateSegmentBlock(ChainSegment $segment, BlockIndexInterface $index)
    {
        /** @var BlockIndexInterface $blockIndex */
        $blockIndex = $this->segmentBlock[$segment];
        if (!$blockIndex->isNext($index)) {
            throw new \RuntimeException('BlockIndex does not follow this block');
        }

        $this->segmentBlock[$segment] = $index;
    }

    /**
     * @param ChainSegment $segment
     * @return int
     */
    public function getPreviousId(ChainSegment $segment)
    {
        if (!isset($this->chainLink[$segment->getId()])) {
            throw new \RuntimeException('No previous segment found - this is really bad!');
        }

        return $this->chainLink[$segment->getId()];
    }

    /**
     * @param ChainSegment $segment
     * @return ChainSegment[]
     */
    public function getHistory(ChainSegment $segment)
    {
        $current = $segment;
        $history = [$segment];
        while ($current->getStart() !== '0') {
            $prev = $this->getPreviousId($current);
            $current = $this->segments[$prev];
            $history[] = $current;
        }

        return array_reverse($history);
    }

    /**
     * @param ChainSegment $segment
     * @return ChainSegment[]
     */
    public function getHashes(ChainSegment $segment)
    {
        return $this->hashStorage[$segment->getId()];
    }

    /**
     * @return ChainSegment[]
     */
    public function getSegments()
    {
        return $this->segments;
    }

    /**
     * @param ChainSegment $segment
     * @return ChainView
     */
    public function view(ChainSegment $segment)
    {
        return new ChainView($this, $segment, $this->segmentBlock->offsetGet($segment));
    }

    /**
     * @param ChainViewInterface $view
     * @return ChainAccess
     */
    public function access(ChainViewInterface $view)
    {
        return new ChainAccess($this->db, $view);
    }

    /**
     * @param ChainSegment $segment
     * @return GuidedChainView
     */
    public function blocks(ChainSegment $segment)
    {
        return new GuidedChainView($this, $this->view($segment), $this->segmentBlock->offsetGet($segment));
    }

    /**
     * @param Math $math
     * @return ChainView
     */
    public function best(Math $math)
    {
        $segments = $this->segments;
        if (count($this->segments) > 1) {
            usort($segments, new ChainWorkComparator($math));
        }

        $best = end($segments);
        return $this->view($best);
    }

    /**
     * @param BufferInterface $buffer
     * @return bool
     */
    public function isKnownHeader(BufferInterface $buffer)
    {
        $binary = $buffer->getBinary();
        foreach (array_keys($this->segments) as $segment) {
            if (isset($this->hashStorage[$segment][$binary])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param BufferInterface $hash
     * @return bool|ChainView
     */
    public function isTip(BufferInterface $hash)
    {
        foreach ($this->segments as $segment) {
            if ($hash->equals($segment->getLast()->getHash())) {
                return $this->view($segment);
            }
        }

        return false;
    }
}