<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Buffertools\BufferInterface;

class Transactions
{

    /**
     * @var DbInterface
     */
    private $db;

    /**
     * Transaction constructor.
     * @param DbInterface $db
     */
    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @param BufferInterface $tipHash
     * @param BufferInterface $txid
     * @return \BitWasp\Bitcoin\Transaction\Transaction|\PDOStatement
     */
    public function fetch(BufferInterface $tipHash, BufferInterface $txid)
    {
        return $this->db->getTransaction($tipHash, $txid);
    }
}
