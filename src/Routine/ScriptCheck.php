<?php

namespace BitWasp\Bitcoin\Node\Routine;


use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chain\UtxoView;
use BitWasp\Bitcoin\Script\ConsensusFactory;
use BitWasp\Bitcoin\Transaction\TransactionInterface;

class ScriptCheck implements ScriptCheckInterface
{

    /**
     * @var ConsensusFactory
     */
    private $consensus;

    /**
     * @param EcAdapterInterface $adapter
     */
    public function __construct(EcAdapterInterface $adapter)
    {
        $this->consensus = new ConsensusFactory($adapter);
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
        $consensus = $this->consensus->getConsensus($flags);
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

        return $result;
    }
}