<?php

namespace BitWasp\Bitcoin\Node\Services\Retarget;

use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;

class RetargetDb
{
    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * ServiceDb constructor.
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->checkHaveRecordStmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM retarget r where r.hash = :hash");
        $this->insertRecordStmt = $this->pdo->prepare("INSERT INTO retarget (hash, prevTime, difference) values (:hash, :prevTime, :difference)");
    }

    public function haveDetails(ChainStateInterface $state)
    {
        $index = $state->getIndex();
        $this->checkHaveRecordStmt->execute(['hash' => $index->getHash()->getBinary()]);
        $result = $this->checkHaveRecordStmt->fetch(\PDO::FETCH_COLUMN);
        return $result == 1;
    }

    public function insertDetails(ChainStateInterface $state, $prevTime, $diffAdjust)
    {
        $index = $state->getIndex();
        $this->insertRecordStmt->execute(['hash' => $index->getHash()->getBinary(), 'prevTime' => $prevTime, 'difference' => $diffAdjust]);
    }
}
