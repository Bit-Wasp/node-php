<?php

namespace BitWasp\Bitcoin\Node\Services\Retarget;


use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\Params;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Node\NodeInterface;
use Pimple\Container;

class RetargetService
{
    /**
     * @var DbInterface
     */
    private $db;

    private $retargetDb;

    public function __construct(NodeInterface $node, Container $container)
    {
        $this->db = $container['db'];
        $this->retargetDb = new RetargetDb($this->db->getPdo());
        $this->math = Bitcoin::getMath();
        $this->consensus = new Consensus(Bitcoin::getMath(), new Params(Bitcoin::getMath()));
        
        $node->chains()->on('retarget', [$this, 'onRetarget']);
    }

    public function onRetarget(ChainStateInterface $state)
    {
        $have = $this->retargetDb->haveDetails($state);
        if (!$have) {
            $prev = $state->fetchAncestor($state->getIndex()->getHeight() - $this->consensus->getParams()->powRetargetInterval());
            $prevTime = $prev->getHeader()->getTimestamp();
            $actual = $this->consensus->calculateWorkTimespan($prevTime, $state->getIndex()->getHeader());
            $ratio = $this->consensus->getParams()->powTargetTimespan() / $actual;
            $timespanAdjust = (1 - $ratio);
            $diffAdjust = -($timespanAdjust*100);
            //$diffAdjust = number_format($diffAdjust, 5, '.', '');
            echo "Hash: ".$state->getIndex()->getHash()->getHex().PHP_EOL;
            echo "Diff change: " . $diffAdjust.PHP_EOL;

            $this->insert($state, $prevTime, $diffAdjust);
        }
    }

    public function insert(ChainStateInterface $state, $prevTime, $difference)
    {
        $this->retargetDb->insertDetails($state, $prevTime, $difference);
    }
}