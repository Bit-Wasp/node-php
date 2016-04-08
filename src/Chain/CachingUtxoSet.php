<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\Buffer;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\RedisCache;

class CachingUtxoSet
{
    /**
     * @var DbInterface
     */
    private $db;

    /**
     * @var RedisCache
     */
    private $set;

    private $cacheHits = [];

    /**
     * @var OutPointSerializer
     */
    private $outpointSerializer;
    private $useCaching = false;
    /**
     * UtxoSet constructor.
     * @param DbInterface $db
     */
    public function __construct(DbInterface $db)
    {
        $this->db = $db;

        if (class_exists('Redis')) {
            $redis = new \Redis();
            $redis->connect('127.0.0.1', 6379);
            $this->set = new RedisCache();
            $this->set->setRedis($redis);
        } else {
            $this->set = new ArrayCache();
        }

        $this->outpointSerializer = new OutPointSerializer();
    }

    /**
     * @param OutPointInterface[] $deleteOutPoints
     * @param Utxo[] $newUtxos
     */
    public function applyBlock(array $deleteOutPoints, array $newUtxos)
    {
        $this->db->transaction(function () use ($deleteOutPoints, $newUtxos) {
            //if (!empty($this->cacheHits)) {
//                $this->db->appendUtxoViewKeys($this->cacheHits);
//            }
            
            $this->db->updateUtxoView($this->outpointSerializer, $deleteOutPoints, $newUtxos, []);
            //$this->db->updateUtxoSet($this->outpointSerializer, $deleteOutPoints, $newUtxos, $this->cacheHits);

        });

        if ($this->useCaching) {
            foreach ($this->cacheHits as $key) {
                $this->set->delete($key);
            }

            foreach ($newUtxos as $c => $utxo) {
                $new = $this->outpointSerializer->serialize($utxo->getOutPoint())->getBinary();
                $this->set->save($new, [
                    $newUtxos[$c]->getOutput()-> getValue(),
                    $newUtxos[$c]->getOutput()->getScript()->getBinary(),
                ], 500000);
            }
        }

        echo "Inserts: " . count($newUtxos). " | Deletes: " . count($deleteOutPoints). " | " . "CacheHits: " . count($this->cacheHits) .PHP_EOL;

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
            $a = 0;
            $b = 0;
            foreach ($requiredOutpoints as $c => $outpoint) {
                if ($this->useCaching) {
                    $key = $this->outpointSerializer->serialize($outpoint)->getBinary();
                    if ($this->set->contains($key)) {
                        list ($value, $scriptPubKey) = $this->set->fetch($key);
                        $cacheHits[] = $key;
                        $utxos[] = new Utxo($outpoint, new TransactionOutput($value, new Script(new Buffer($scriptPubKey))));
                        $a++;
                        continue;
                    }
                }

                $required[] = $outpoint;
            }

            if (empty($required) === false) {
                $utxos = array_merge($utxos, $this->db->createMiniUtxoView($this->outpointSerializer, $required));
            }

            $this->cacheHits = $cacheHits;

            return $utxos;
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL . $e->getTraceAsString().PHP_EOL;
            throw new \RuntimeException('Failed to find UTXOS in set');
        }
    }
}
