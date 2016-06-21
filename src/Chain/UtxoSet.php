<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Node\Db\DbInterface;
use BitWasp\Bitcoin\Node\Serializer\Transaction\CachingOutPointSerializer;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializerInterface;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Utxo\Utxo;
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
     * @param OutPointInterface[] $deleteOutPoints
     * @param Utxo[] $newUtxos
     */
    public function applyBlock(array $deleteOutPoints, array $newUtxos)
    {
        $this->db->updateUtxoSet($this->outpointSerializer, $deleteOutPoints, $newUtxos);
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
