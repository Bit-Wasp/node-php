<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Node\Db\DbInterface;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
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
     * @var OutPointSerializer
     */
    private $outpointSerializer;

    /**
     * UtxoSet constructor.
     * @param DbInterface $db
     */
    public function __construct(DbInterface $db)
    {
        $this->db = $db;
        $this->outpointSerializer = new OutPointSerializer();
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
        try {
            
            $utxos = $this->db->fetchUtxoDbList($this->outpointSerializer, $required);
            

            return $utxos;
        } catch (\Exception $e) {
            echo "Internal: ".$e->getMessage().PHP_EOL;
            
            throw new \RuntimeException('Failed to find UTXOS in set');
        }
    }
}
