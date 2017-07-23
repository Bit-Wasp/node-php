<?php

namespace BitWasp\Bitcoin\Node\Consensus;

use BitWasp\Bitcoin\Script\Consensus\Exception\BitcoinConsensusException;
use BitWasp\Bitcoin\Script\Interpreter\InterpreterInterface;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Serializer\Transaction\TransactionSerializer;
use BitWasp\Bitcoin\Serializer\Transaction\TransactionSerializerInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Script\Consensus\ConsensusInterface;

class BitcoinConsensus implements ConsensusInterface
{
    /**
     * @var TransactionSerializerInterface
     */
    private $txSerializer;

    /**
     * BitcoinConsensus constructor.
     * @param TransactionSerializerInterface $txSerializer
     */
    public function __construct(TransactionSerializerInterface $txSerializer)
    {
        $this->txSerializer = $txSerializer;
    }

    /**
     * @param TransactionInterface $tx
     * @param ScriptInterface $scriptPubKey
     * @param int $nInputToSign
     * @param int $flags
     * @param int $amount
     * @return bool
     */
    public function verify(TransactionInterface $tx, ScriptInterface $scriptPubKey, $flags, $nInputToSign, $amount)
    {
        if ($flags !== ($flags & BITCOINCONSENSUS_VERIFY_ALL)) {
            throw new BitcoinConsensusException("Invalid flags for bitcoinconsensus");
        }

        $error = 0;
        if ($flags & InterpreterInterface::VERIFY_WITNESS) {
            $txBin = $this->txSerializer->serialize($tx);
            $verify = (bool) bitcoinconsensus_verify_script_with_amount($scriptPubKey->getBinary(), $amount, $txBin->getBinary(), $nInputToSign, $flags, $error);
        } else {
            $txBin = $this->txSerializer->serialize($tx, TransactionSerializer::NO_WITNESS);
            $verify = (bool) bitcoinconsensus_verify_script($scriptPubKey->getBinary(), $txBin->getBinary(), $nInputToSign, $flags, $error);
        }

        return $verify;
    }
}
