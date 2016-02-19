<?php

namespace BitWasp\Bitcoin\Node\Index;

use BitWasp\Bitcoin\Node\Db;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;

class Transaction
{

    /**
     * @var Db
     */
    private $db;

    /**
     * Transaction constructor.
     * @param Db $db
     */
    public function __construct(Db $db)
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
