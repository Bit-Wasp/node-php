<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
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
     * @var Math
     */
    private $math;

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
     * Map of height => hash ..
     *
     * @var array
     */
    private $heightStorage = [];

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
     * @var ChainSegment
     */
    private $best;

    /**
     * ChainContainer constructor.
     * @param Math $math
     * @param ParamsInterface $params
     * @param array $segments
     */
    public function __construct(Math $math, ParamsInterface $params, array $segments = [])
    {
        $this->math = $math;
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

        $this->updateGreatestWork();
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
            $this->heightStorage[$id][$row['height']] = $row['hash'];
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
        $prevBits = $segment->getLast()->getHeader()->getBits();
        $segment->next($index);
        $this->hashStorage[$segment->getId()][$index->getHash()->getBinary()] = $index->getHeight();
        $this->heightStorage[$segment->getId()][$index->getHeight()] = $index->getHash()->getBinary();
        if (($index->getHeight() % $this->params->powRetargetInterval()) === 0) {
            $this->emit('retarget', [$segment, $prevBits, $index]);
        }

        $this->updateGreatestWork();
    }

    private function updateGreatestWork()
    {
        $segments = $this->segments;
        if (count($this->segments) > 1) {
            usort($segments, new ChainWorkComparator(Bitcoin::getMath()));
        }

        $best = end($segments);
        if (is_null($this->best) || ($this->best instanceof ChainSegment && $this->best->getLast() !== $best->getLast())) {
            $this->best = $best;
        }
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
     * @param ChainSegment $segment
     * @return ChainSegment[]
     */
    public function getHeights(ChainSegment $segment)
    {
        return $this->heightStorage[$segment->getId()];
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
     * @param ChainViewInterface $view
     * @return GuidedChainView
     */
    public function blocksView(ChainViewInterface $view)
    {
        return new GuidedChainView($this, $view, $this->segmentBlock->offsetGet($view->getSegment()));
    }

    /**
     * @param ChainSegment $segment
     * @return GuidedChainView
     */
    public function blocks(ChainSegment $segment)
    {
        return $this->blocksView($this->view($segment));
    }

    /**
     * @return ChainView
     */
    public function best()
    {
        return $this->view($this->best);
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


    /**
     * @param BlockHeaderInterface $header
     * @return BlockIndexInterface|bool
     */
    public function hasBlockTip(BlockHeaderInterface $header)
    {
        foreach ($this->segments as $segment) {
            /** @var BlockIndexInterface $segBlock */
            $segBlock = $this->segmentBlock->offsetGet($segment);
            if ($header->getPrevBlock()->equals($segBlock->getHash())) {
                return $segBlock;
            }
        }

        return false;
    }
}