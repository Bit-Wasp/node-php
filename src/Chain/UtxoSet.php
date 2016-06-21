<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Node\Db\DbInterface;
use BitWasp\Bitcoin\Node\Index\Validation\BlockData;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializerInterface;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Utxo\UtxoInterface;

class UtxoSet
{
    /**
     * @var DbInterface
     */
    private $db;

    /**
     * @var OutPointSerializerInterface
     */
    private $outpointSerializer;

    /**
     * UtxoSet constructor.
     * @param DbInterface $db
     */
    public function __construct(DbInterface $db, OutPointSerializerInterface $outpointSerializer)
    {
        $this->db = $db;
        $this->outpointSerializer = $outpointSerializer ?: new OutPointSerializer();
    }

    /**
     * @param BlockData $blockData
     */
    public function applyBlock(BlockData $blockData)
    {
        $this->db->updateUtxoSet($this->outpointSerializer, $blockData);
    }

    /**
     * @param OutPointInterface[] $required
     * @return UtxoInterface[]
     */
    public function fetchView(array $required)
    {
        return $this->db->fetchUtxoDbList($this->outpointSerializer, $required);
    }
}
