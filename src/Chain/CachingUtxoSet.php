<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\Buffer;

class CachingUtxoSet
{
    /**
     * @var DbInterface
     */
    private $db;

    private $set = [];
    private $cacheHits = [];

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
        $this->db->updateUtxoSet($deleteOutPoints, $newUtxos, $this->cacheHits);

        foreach ($this->cacheHits as $key) {
            unset($this->set[$key]);
        }

        foreach ($newUtxos as $c => $utxo) {
            $new = $this->outpointSerializer->serialize($utxo->getOutPoint())->getBinary();
            $this->set[$new] = [
                $newUtxos[$c]->getOutput()-> getValue(),
                $newUtxos[$c]->getOutput()->getScript()->getBinary(),
            ];
        }

        echo "Inserts: " . count($newUtxos). " | Deletes: " . count($deleteOutPoints). " | " . "CacheGits: " . count($this->cacheHits) . " Total size: " . count($this->set).PHP_EOL;

        $this->cacheHits = [];
    }

    /**
     * @param OutPointInterface[] $requiredOutpoints
     * @return UtxoView
     */
    public function fetchView(array $requiredOutpoints)
    {
        try {
            $utxos = [];
            $required = [];
            $cacheHits = [];
            foreach ($requiredOutpoints as $c => $outpoint) {
                $key = $this->outpointSerializer->serialize($outpoint)->getBinary();
                if (array_key_exists($key, $this->set)) {
                    list ($value, $scriptPubKey) = $this->set[$key];
                    $cacheHit[] = $key;
                    $utxos[] = new Utxo($outpoint, new TransactionOutput($value, new Script(new Buffer($scriptPubKey))));
                } else {
                    $required[] = $outpoint;
                }
            }

            if (empty($required) === false) {
                $utxos = array_merge($utxos, $this->db->fetchUtxoDbList($required));
            }

            $this->cacheHits = $cacheHits;

            return $utxos;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to find UTXOS in set');
        }
    }

}