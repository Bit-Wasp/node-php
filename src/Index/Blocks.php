<?php

namespace BitWasp\Bitcoin\Node\Index;


use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\BlockIndex;
use BitWasp\Bitcoin\Node\ChainState;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\MySqlDb;
use BitWasp\Bitcoin\Node\Params;
use BitWasp\Bitcoin\Node\UtxoView;
use BitWasp\Bitcoin\Script\ConsensusFactory;
use BitWasp\Bitcoin\Script\Interpreter\InterpreterInterface;
use BitWasp\Bitcoin\Transaction\Locktime;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\Buffer;

class Blocks
{
    /**
     * @var EcAdapterInterface
     */
    private $adapter;

    /**
     * @var ProofOfWork
     */
    private $pow;

    /**
     * @var MySqlDb
     */
    private $db;

    /**
     * @var Params
     */
    private $params;

    /**
     * @var \BitWasp\Bitcoin\Block\BlockInterface
     */
    private $genesis;

    /**
     * @var string
     */
    private $genesisHash;

    /**
     * @param MySqlDb $db
     * @param EcAdapterInterface $ecAdapter
     * @param Params $params
     * @param ProofOfWork $pow
     */
    public function __construct(
        MySqlDb $db,
        EcAdapterInterface $ecAdapter,
        Params $params,
        ProofOfWork $pow
    ) {
        $this->db = $db;
        $this->adapter = $ecAdapter;
        $this->params = $params;
        $this->pow = $pow;
        $this->consensus = new Consensus($this->adapter->getMath(), $this->params);
        $this->genesis = $params->getGenesisBlock();
        $this->genesisHash = $this->genesis->getHeader()->getBlockHash();
        $this->init();
    }

    /**
     *
     */
    public function init()
    {
        try {
            $this->db->fetchBlock($this->genesisHash);
        } catch (\Exception $e) {
            $this->db->insertBlockGenesis($this->genesis);
        }
    }

    /**
     * @param UtxoView $view
     * @param TransactionInterface $tx
     * @param $spendHeight
     * @return bool
     */
    public function contextualCheckInputs(UtxoView $view, TransactionInterface $tx, $spendHeight)
    {
        $math = $this->adapter->getMath();

        $valueIn = 0;
        for ($i = 0; $i < count($tx->getInputs()); $i++) {
            $utxo = $view->fetchByInput($tx->getInput($i));
            /*if ($out->isCoinbase()) {
                // todo: cb / height
                if ($spendHeight - $out->getHeight() < $this->params->coinbaseMaturityAge()) {
                    return false;
                }
            }*/

            $value = $utxo->getOutput()->getValue();
            $valueIn = $math->add($value, $valueIn);
            if ( !$this->consensus->checkAmount($valueIn) || !$this->consensus->checkAmount($value)) {
                return false;
            }
        }

        // todo: replace with Tx:getValueOut
        $valueOut = 0;
        $outputs = $tx->getOutputs()->getOutputs();
        foreach ($outputs as $output) {
            $valueOut = $math->add($output->getValue(), $valueOut);
            if ($this->consensus->checkAmount($valueOut) || $this->consensus->checkAmount($output->getValue())) {
                return false;
            }
        }

        if ($math->cmp($valueIn, $valueOut) < 0) {
            return false;
        }

        $fee = $valueIn - $valueOut;
        if ($math->cmp($fee, 0) < 0) {
            return false;
        }

        if (!$this->consensus->checkAmount($fee)) {
            return false;
        }

        return true;
    }

    /**
     * @param TransactionInterface $tx
     * @param int $height
     * @param Flags $flags
     * @return bool
     */
    public function checkInputs(UtxoView $view, TransactionInterface $tx, $height, Flags $flags, $checkScripts = true)
    {
        if (!$tx->isCoinbase()) {
            $this->contextualCheckInputs($view, $tx, $height);

            if ($checkScripts) {
                $nInputs = count($tx->getInputs());
                for ($i = 0; $i < $nInputs; $i++) {
                    echo " . ";
                    $factory = new ConsensusFactory($this->adapter);
                    $consensus = $factory->getConsensus($flags);
                    $input = $tx->getInput($i);
                    $script = $view->fetchByInput($input)
                        ->getOutput()
                        ->getScript();
                    $verify = $consensus->verify($tx, $script, $i);

                    if (!$verify) {
                        throw new \RuntimeException("CheckInputs: Input $i failed script verification");
                    }
                }
            }
        }

        return true;
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
        if (0 == $nLockTime) {
            return true;
        }

        $math = $this->adapter->getMath();
        $basis = $math->cmp($nLockTime, Locktime::BLOCK_MAX) < 0 ? $height : $time;
        if ($math->cmp($nLockTime, $basis) < 0) {
            return true;
        }

        $inputs = $tx->getInputs()->getInputs();
        $isFinal = true;
        foreach ($inputs as $in) {
            $isFinal &= $in->isFinal();
        }

        return $isFinal;
    }

    /**
     * @param TransactionInterface $transaction
     * @param bool|true $checkSize
     * @return bool
     */
    public function checkTransaction(TransactionInterface $transaction, $checkSize = true)
    {
        // Must be one txin, and one txout
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

        // Check output values
        $math = $this->adapter->getMath();
        $value = 0;
        foreach ($outputs->getOutputs() as $out) {
            if ($math->cmp($out->getValue(), 0) < 0) {
                throw new \RuntimeException('CheckTransaction: tx.out error 1');
            }
            if (!$this->consensus->checkAmount($out->getValue())) {
                throw new \RuntimeException('CheckTransaction: tx.out error 2');
            }
            $value = $math->add($value, $out->getValue());
            if ($math->cmp($value, 0) < 0 || !$this->consensus->checkAmount($value)) {
                throw new \RuntimeException('CheckTransaction: tx.out error 3');
            }
        }

        // Avoid duplicate inputs
        $ins = array();
        foreach ($inputs->getInputs() as $in) {
            $ins[] = $in->getTransactionId() . $in->getVout();
        }

        $truncated = array_keys(array_flip($ins));
        if (count($truncated) !== $nInputs) {
            throw new \RuntimeException('CheckTransaction: duplicate inputs');
        }
        unset($ins);

        if ($transaction->isCoinbase()) {
            $first = $transaction->getInput(0);
            $parsed = $first->getScript()->getScriptParser()->parse();

            array_filter($parsed, function ($var) {
                return !$var instanceof Buffer;
            });
            $size = count($parsed);
            if ($size < 2 || $size > 100) {
                throw new \RuntimeException('CheckTransaction: coinbase scriptSig fails constraints');
            }
        } else {
            foreach ($inputs->getInputs() as $input) {
                if ($input->isCoinBase()) {
                    throw new \RuntimeException('CheckTransaction: a transaction input was null');
                }
            }
        }

        return true;
    }

    /**
     * @param BlockInterface $block
     * @param BlockIndex $prevBlockIndex
     * @return bool
     */
    public function checkContextual(BlockInterface $block, BlockIndex $prevBlockIndex)
    {
        $newHeight = $prevBlockIndex->getHeight() + 1;
        $newTime = $block->getHeader()->getTimestamp();
        $txs = $block->getTransactions();
        for ($i = 0, $c = count($block->getTransactions()); $i < $c; $i++) {
            if (!$this->checkTransactionIsFinal($txs->getTransaction($i), $newHeight, $newTime)) {
                throw new \RuntimeException('Block contains a non-final transaction');
            }
        }

        return true;
    }

    /**
     * @param BlockInterface $block
     * @return bool
     * @throws \Exception
     */
    public function check(BlockInterface $block)
    {
        $header = $block->getHeader();
        if ($block->getMerkleRoot() !== $header->getMerkleRoot()) {
            throw new \RuntimeException('Blocks::check(): failed to verify merkle root');
        }

        $transactions = $block->getTransactions();
        $txCount = count($transactions);
        if ($txCount == 0 || $block->getBuffer()->getSize() > $this->params->maxBlockSizeBytes()) {
            throw new \RuntimeException('Blocks::check(): Zero transactions, or block exceeds max size');
        }

        // The first transaction is coinbase, and only the first transaction is coinbase.
        if (!$transactions->getTransaction(0)->isCoinbase()) {
            throw new \RuntimeException('Blocks::check(): First transaction was not coinbase');
        }

        for ($i = 1; $i < $txCount; $i++) {
            if ($transactions->getTransaction($i)->isCoinbase()) {
                throw new \RuntimeException('Blocks::check(): more than one coinbase');
            }
        }

        for ($i = 0; $i < $txCount; $i++) {
            if (!$this->checkTransaction($transactions->getTransaction($i))) {
                throw new \RuntimeException('Blocks::check(): failed checkTransaction');
            }
        }

        // todo: sigops

        return $this;

    }

    /**
     * @param ChainState $state
     * @param BlockInterface $block
     * @param Headers $headers
     * @return BlockIndex|false
     * @throws \Exception
     */
    public function accept(ChainState $state, BlockInterface $block, Headers $headers)
    {
        $math = $this->adapter->getMath();
        $bestBlock = $state->getLastBlock();

        try {
            $index = $headers->accept($state, $block->getHeader());
            if (!$this->check($block) || !$this->checkContextual($block, $bestBlock)) {
                throw new \RuntimeException('We cannot yet deal with out of sequence blocks');
            }

            $view = $this->db->fetchUtxoView($block, $state->getChain());

            $flagP2sh = $math->cmp($bestBlock->getHeader()->getTimestamp(), $this->params->p2shActivateTime()) >= 0;
            $flags = new Flags($flagP2sh ? InterpreterInterface::VERIFY_P2SH : InterpreterInterface::VERIFY_NONE);

        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
            throw $e;
        }

        $nInputs = 0;
        $nFees = 0;
        $nSigOps = 0;
        $txs = $block->getTransactions();
        for ($i = 0, $nTx = count($txs); $i < $nTx; $i++) {
            $tx = $txs->getTransaction($i);
            $nInputs += count($tx->getInputs());

            if (!$tx->isCoinbase()) {
                if ($flagP2sh) {
                    // sigops p2sh
                }

                $fee = $math->sub($view->getValueIn($math, $tx), $tx->getValueOut());
                $nFees = $math->add($nFees, $fee);
                if (!$this->checkInputs($view, $tx, $index->getHeight(), $flags)) {
                    throw new \RuntimeException('Accept(): failed on check inputs ' . $tx->getTransactionId());
                }
            }
        }

        $nBlockReward = $math->add($this->consensus->getSubsidy($index->getHeight()), $nFees);
        if ($math->cmp($block->getTransactions()->getTransaction(0)->getValueOut(), $nBlockReward) > 0) {
            throw new \RuntimeException('Accept(): Coinbase pays too much');
        }

        $this->db->insertBlock($block);

        return $index;
    }
}