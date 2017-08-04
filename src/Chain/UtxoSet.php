<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Node\Db\DbInterface;
use BitWasp\Bitcoin\Node\Index\Validation\BlockData;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializerInterface;

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
     * @param OutPointSerializerInterface $outpointSerializer
     */
    public function __construct(DbInterface $db, OutPointSerializerInterface $outpointSerializer)
    {
        $this->db = $db;
        $this->outpointSerializer = $outpointSerializer;
    }

    /**
     * @param BlockData $blockData
     */
    public function applyBlock(BlockData $blockData)
    {
        $this->db->updateUtxoSet($this->outpointSerializer, $blockData);
    }

    /**
     * @param BlockData $blockData
     * @return \BitWasp\Bitcoin\Utxo\Utxo[]
     */
    public function fetchView(BlockData $blockData)
    {
        return $this->db->fetchUtxoDbList($this->outpointSerializer, $blockData->requiredOutpoints);
    }
}
