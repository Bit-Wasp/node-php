<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\TransactionInterface;

class ScriptCheck implements ScriptCheckInterface
{

    /**
     * @var EcAdapterInterface
     */
    private $adapter;

    /**
     * @param EcAdapterInterface $adapter
     */
    public function __construct(EcAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @param UtxoView $utxoView
     * @param TransactionInterface $tx
     * @param Flags $flags
     * @return bool
     */
    public function check(UtxoView $utxoView, TransactionInterface $tx, Flags $flags)
    {
        $result = true;
        $consensus = ScriptFactory::consensus($flags);
        for ($i = 0, $c = count($tx->getInputs()); $i < $c; $i++) {
            $result &= $consensus->verify(
                $tx,
                $utxoView
                    ->fetchByInput($tx->getInput($i))
                    ->getOutput()
                    ->getScript(),
                $i
            );
        }

        return (bool) $result;
    }
}
