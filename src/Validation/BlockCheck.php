<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Collection\Transaction\TransactionInputCollection;
use BitWasp\Bitcoin\Collection\Transaction\TransactionOutputCollection;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Locktime;
use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;

class BlockCheck implements BlockCheckInterface
{
    /**
     * @var Consensus
     */
    private $consensus;

    /**
     * @var \BitWasp\Bitcoin\Math\Math
     */
    private $math;

    /**
     * @var ParamsInterface
     */
    private $params;

    /**
     * @var ScriptCheckInterface
     */
    private $scriptCheck;

    /**
     * @param Consensus $consensus
     * @param EcAdapterInterface $ecAdapter
     * @param ScriptCheckInterface $scriptCheck
     */
    public function __construct(Consensus $consensus, EcAdapterInterface $ecAdapter, ScriptCheckInterface $scriptCheck)
    {
        $this->consensus = $consensus;
        $this->math = $ecAdapter->getMath();
        $this->scriptCheck = $scriptCheck;
        $this->params = $consensus->getParams();
    }

    /**
     * @param TransactionInterface $tx
     * @return int
     */
    public function getLegacySigOps(TransactionInterface $tx)
    {
        $nSigOps = 0;
        foreach ($tx->getInputs() as $input) {
            $nSigOps += $input->getScript()->countSigOps(false);
        }

        foreach ($tx->getOutputs() as $output) {
            $nSigOps += $output->getScript()->countSigOps(false);
        }

        return $nSigOps;
    }

    /**
     * @param UtxoView $view
     * @param TransactionInterface $tx
     * @return int
     */
    public function getP2shSigOps(UtxoView $view, TransactionInterface $tx)
    {
        if ($tx->isCoinbase()) {
            return 0;
        }

        $nSigOps = 0;
        $scriptPubKey = ScriptFactory::scriptPubKey();
        for ($i = 0, $c = count($tx->getInputs()); $i < $c; $i++) {
            $input = $tx->getInput($i);
            $outputScript = $view
                ->fetchByInput($input)
                ->getOutput()
                ->getScript();

            if ($scriptPubKey->classify($outputScript)->isPayToScriptHash()) {
                $nSigOps += $outputScript->countP2shSigOps($input->getScript());
            }
        }

        return $nSigOps;
    }

    /**
     * @param TransactionInterface $coinbase
     * @param int $nFees
     * @param int $height
     * @return $this
     */
    public function checkCoinbaseSubsidy(TransactionInterface $coinbase, $nFees, $height)
    {
        $nBlockReward = $this->math->add($this->consensus->getSubsidy($height), $nFees);
        if ($this->math->cmp($coinbase->getValueOut(), $nBlockReward) > 0) {
            throw new \RuntimeException('Accept(): Coinbase pays too much');
        }

        return $this;
    }

    /**
     * @param TransactionInterface $tx
     * @param int $height
     * @param int $time
     * @return bool|int
     */
    public function checkTransactionIsFinal(TransactionInterface $tx, $height, $time)
    {
        $nLockTime = $tx->getLockTime();
        if (0 === $nLockTime) {
            return true;
        }

        $basis = $this->math->cmp($nLockTime, Locktime::BLOCK_MAX) < 0 ? $height : $time;
        if ($this->math->cmp($nLockTime, $basis) < 0) {
            return true;
        }

        $isFinal = true;
        foreach ($tx->getInputs() as $input) {
            $isFinal &= $input->isFinal();
        }

        return $isFinal;
    }

    /**
     * @param TransactionOutputCollection $outputs
     * @return $this
     */
    public function checkOutputsAmount(TransactionOutputCollection $outputs)
    {
        // Check output values
        $value = 0;
        foreach ($outputs as $output) {
            if ($this->math->cmp($output->getValue(), 0) < 0) {
                throw new \RuntimeException('CheckOutputsAmount: value negative');
            }

            if (!$this->consensus->checkAmount($output->getValue())) {
                throw new \RuntimeException('CheckOutputsAmount: invalid amount');
            }

            $value = $this->math->add($value, $output->getValue());
            if ($this->math->cmp($value, 0) < 0 || !$this->consensus->checkAmount($value)) {
                throw new \RuntimeException('CheckOutputsAmount: invalid total amount');
            }
        }

        return $this;
    }

    /**
     * @param TransactionInputCollection $inputs
     * @return $this
     */
    public function checkInputsForDuplicates(TransactionInputCollection $inputs)
    {
        // Avoid duplicate inputs
        $ins = array();
        foreach ($inputs as $input) {
            $outpoint = $input->getOutPoint();
            $ins[] = $outpoint->getTxId()->getBinary() . $outpoint->getVout();
        }

        $truncated = array_keys(array_flip($ins));
        if (count($truncated) !== count($inputs)) {
            throw new \RuntimeException('CheckTransaction: duplicate inputs');
        }

        return $this;
    }

    /**
     * @param TransactionInterface $transaction
     * @param bool|true $checkSize
     * @return $this
     */
    public function checkTransaction(TransactionInterface $transaction, $checkSize = true)
    {
        // Must be at least one transaction input and output
        $inputs = $transaction->getInputs();
        $nInputs = count($inputs);
        if (0 === $nInputs) {
            throw new \RuntimeException('CheckTransaction: no inputs');
        }

        $outputs = $transaction->getOutputs();
        $nOutputs = count($outputs);
        if (0 === $nOutputs) {
            throw new \RuntimeException('CheckTransaction: no outputs');
        }

        if ($checkSize && $transaction->getBuffer()->getSize() > $this->params->maxBlockSizeBytes()) {
            throw new \RuntimeException('CheckTransaction: tx size exceeds max block size');
        }

        $this
            ->checkOutputsAmount($outputs)
            ->checkInputsForDuplicates($inputs);

        if ($transaction->isCoinbase()) {
            $first = $transaction->getInput(0);
            $scriptSize = $first->getScript()->getBuffer()->getSize();
            if ($scriptSize < 2 || $scriptSize > 100) {
                throw new \RuntimeException('CheckTransaction: coinbase scriptSig fails constraints');
            }
        } else {
            foreach ($inputs as $input) {
                if ($input->isCoinBase()) {
                    throw new \RuntimeException('CheckTransaction: a non-coinbase transaction input was null');
                }
            }
        }

        return $this;
    }

    /**
     * @param BlockInterface $block
     * @return $this
     */
    public function check(BlockInterface $block)
    {
        $header = $block->getHeader();
        if ($block->getMerkleRoot() != $header->getMerkleRoot()) {
            throw new \RuntimeException('Blocks::check(): failed to verify merkle root');
        }

        $transactions = $block->getTransactions();
        $txCount = count($transactions);
        if (0 === $txCount || $block->getBuffer()->getSize() > $this->params->maxBlockSizeBytes()) {
            throw new \RuntimeException('Blocks::check(): Zero transactions, or block exceeds max size');
        }

        // The first transaction is coinbase, and only the first transaction is coinbase.
        if (!$transactions[0]->isCoinbase()) {
            throw new \RuntimeException('Blocks::check(): First transaction was not coinbase');
        }

        for ($i = 1; $i < $txCount; $i++) {
            if ($transactions[$i]->isCoinbase()) {
                throw new \RuntimeException('Blocks::check(): more than one coinbase');
            }
        }

        $nSigOps = 0;
        foreach ($transactions as $transaction) {
            if (!$this->checkTransaction($transaction)) {
                throw new \RuntimeException('Blocks::check(): failed checkTransaction');
            }

            $nSigOps += $this->getLegacySigOps($transaction);
        }

        if ($this->math->cmp($nSigOps, $this->params->getMaxBlockSigOps()) > 0) {
            throw new \RuntimeException('Blocks::check(): out-of-bounds sigop count');
        }

        return $this;
    }

    /**
     * @param UtxoView $view
     * @param TransactionInterface $tx
     * @param $spendHeight
     * @return $this
     */
    public function checkContextualInputs(UtxoView $view, TransactionInterface $tx, $spendHeight)
    {
        $valueIn = 0;
        $nInputs = count($tx->getInputs());
        for ($i = 0; $i < $nInputs; $i++) {
            $utxo = $view->fetchByInput($tx->getInput($i));
            /*if ($out->isCoinbase()) {
                // todo: cb / height
                if ($spendHeight - $out->getHeight() < $this->params->coinbaseMaturityAge()) {
                    return false;
                }
            }*/

            $value = $utxo->getOutput()->getValue();
            $valueIn = $this->math->add($value, $valueIn);
            if (!$this->consensus->checkAmount($valueIn) || !$this->consensus->checkAmount($value)) {
                throw new \RuntimeException('CheckAmount failed for inputs value');
            }
        }

        $valueOut = 0;
        foreach ($tx->getOutputs() as $output) {
            $valueOut = $this->math->add($output->getValue(), $valueOut);
            if (!$this->consensus->checkAmount($valueOut) || !$this->consensus->checkAmount($output->getValue())) {
                throw new \RuntimeException('CheckAmount failed for outputs value');
            }
        }

        if ($this->math->cmp($valueIn, $valueOut) < 0) {
            throw new \RuntimeException('Value-in is less than value out');
        }

        $fee = $this->math->sub($valueIn, $valueOut);
        if ($this->math->cmp($fee, 0) < 0) {
            throw new \RuntimeException('Fee is less than zero');
        }

        if (!$this->consensus->checkAmount($fee)) {
            throw new \RuntimeException('CheckAmount failed for fee');
        }

        return $this;
    }

    /**
     * @param BlockInterface $block
     * @param BlockIndexInterface $prevBlockIndex
     * @return $this
     */
    public function checkContextual(BlockInterface $block, BlockIndexInterface $prevBlockIndex)
    {
        $newHeight = $prevBlockIndex->getHeight() + 1;
        $newTime = $block->getHeader()->getTimestamp();

        foreach ($block->getTransactions() as $transaction) {
            if (!$this->checkTransactionIsFinal($transaction, $newHeight, $newTime)) {
                throw new \RuntimeException('Block contains a non-final transaction');
            }
        }

        return $this;
    }

    /**
     * @param UtxoView $view
     * @param TransactionInterface $tx
     * @param int $height
     * @param Flags $flags
     * @param bool|true $checkScripts
     * @return $this
     */
    public function checkInputs(UtxoView $view, TransactionInterface $tx, $height, Flags $flags, $checkScripts = true)
    {
        if (!$tx->isCoinbase()) {
            $this->checkContextualInputs($view, $tx, $height);

            if ($checkScripts && !$this->scriptCheck->check($view, $tx, $flags)) {
                throw new \RuntimeException('Script verification failed');
            }
        }

        return $this;
    }
}
