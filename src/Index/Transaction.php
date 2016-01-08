<?php

namespace BitWasp\Bitcoin\Node\Index;


use BitWasp\Bitcoin\Node\Db;
use BitWasp\Buffertools\Buffer;

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
     * @param Buffer $tipHash
     * @param Buffer $txid
     * @return \BitWasp\Bitcoin\Transaction\Transaction|\PDOStatement
     */
    public function fetch(Buffer $tipHash, Buffer $txid)
    {
        return $this->db->getTransaction($tipHash, $txid);
    }
}