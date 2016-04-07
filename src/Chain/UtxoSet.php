<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\TransactionInputInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\Buffer;

class UtxoSet implements \Countable
{
    /**
     * @var array
     */
    private $set = [];

    /**
     * @var OutPointSerializer
     */
    private $outpointSerializer;

    /**
     * UtxoSet constructor.
     * @param \PDOStatement $statement
     */
    public function __construct(\PDOStatement $statement)
    {
        foreach ($statement->fetchAll() as $values) {
            list ($key, $value, $script) = $values;
            $this->set[$key] = [$value, $script];
        }
        $this->outpointSerializer = new OutPointSerializer();
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->set);
    }

    /**
     * @param OutPointInterface[] $deleteOutPoints
     * @param Utxo[] $newUtxos
     */
    public function applyBlock(array $deleteOutPoints, array $newUtxos)
    {
        echo "UtxoSet::applyBlock()\n";
        $cDeleted = [];
        foreach ($deleteOutPoints as $c => $outpoint) {
            $cDeleted[$c] = $this->outpointSerializer->serialize($outpoint)->getBinary();
            if (!array_key_exists($cDeleted[$c], $this->set)) {
                throw new \RuntimeException('UtxoSet: item not found for deletion');
            }
        }

        $cNew = [];
        foreach ($newUtxos as $c => $utxo) {
            $cNew[$c] = $this->outpointSerializer->serialize($utxo->getOutPoint())->getBinary();
            if (array_key_exists($cNew[$c], $this->set)) {
                throw new \RuntimeException('UtxoSet: item for insertion already exists');
            }
        }

        foreach ($cDeleted as $delete) {
            unset($this->set[$delete]);
        }

        foreach ($cNew as $c => $new) {
            $this->set[$new] = [
                $newUtxos[$c]->getOutput()-> getValue(),
                $newUtxos[$c]->getOutput()->getScript()->getBinary(),
            ];
        }

        echo "Inserts: " . count($newUtxos). " | Deletes: " . count($deleteOutPoints). " | " . " Total size: " . count($this->set).PHP_EOL;
    }

    /**
     * @param OutPointInterface $outpoint
     * @return bool
     */
    public function have(OutPointInterface $outpoint)
    {
        $key = $this->outpointSerializer->serialize($outpoint)->getBinary();
        return array_key_exists($key, $this->set);
    }

    /**
     * @param OutPointInterface $outpoint
     * @return Utxo
     */
    public function fetch(OutPointInterface $outpoint)
    {
        $key = $this->outpointSerializer->serialize($outpoint)->getBinary();
        if (!array_key_exists($key, $this->set)) {
            throw new \RuntimeException('UtxoSet: UTXO not found in set: '.$outpoint->getHex(). " - " . $outpoint->getVout());
        }

        list ($value, $scriptPubKey) = $this->set[$key];

        return new Utxo($outpoint, new TransactionOutput($value, new Script(new Buffer($scriptPubKey))));
    }

    /**
     * @param OutPointInterface[] $requiredOutpoints
     * @return UtxoView
     */
    public function fetchView(array $requiredOutpoints)
    {
        try {
            $utxo = [];
            foreach ($requiredOutpoints as $outpoint) {
                $utxo[] = $this->fetch($outpoint);
            }

            return $utxo;
        } catch (\Exception $e) {
            echo $e->getMessage().PHP_EOL;
            throw new \RuntimeException('Failed to find UTXOS in set');
        }
    }

    /**
     * @param TransactionInputInterface $input
     * @return Utxo
     */
    public function fetchByInput(TransactionInputInterface $input)
    {
        return $this->fetch($input->getOutPoint());
    }

    /**
     * @param Math $math
     * @param TransactionInterface $tx
     * @return int|string
     */
    public function getValueIn(Math $math, TransactionInterface $tx)
    {
        $value = 0;
        foreach ($tx->getInputs() as $input) {
            $value = $math->add($value, $this->fetchByInput($input)->getOutput()->getValue());
        }

        return $value;
    }

    /**
     * @param Math $math
     * @param TransactionInterface $tx
     * @return int|string
     */
    public function getFeePaid(Math $math, TransactionInterface $tx)
    {
        $valueIn = $this->getValueIn($math, $tx);
        $valueOut = $tx->getValueOut();

        return $math->sub($valueIn, $valueOut);
    }
}