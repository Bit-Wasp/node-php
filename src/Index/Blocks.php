<?php

namespace BitWasp\Bitcoin\Node\Index;


use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Chain\Difficulty;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Chains;
use BitWasp\Bitcoin\Node\MySqlDb;
use BitWasp\Bitcoin\Node\Params;
use BitWasp\Bitcoin\Script\Script;
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
     * @var Difficulty
     */
    private $difficulty;

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
     * @var Chains
     */
    private $chains;

    public function __construct(MySqlDb $db, EcAdapterInterface $ecAdapter, Params $params, Chains $state)
    {
        $this->chains = $state;
        $this->db = $db;
        $this->adapter = $ecAdapter;
        $this->params = $params;
        $this->genesis = $params->getGenesisBlock();
        $this->genesisHash = $this->genesis->getHeader()->getBlockHash();
        $this->init();
        $this->difficulty = new Difficulty($ecAdapter->getMath(), $params->getLowestBits());
        $this->pow = new ProofOfWork($ecAdapter->getMath(), $this->difficulty, '');
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
     * Must never be coinbase
     * @param TransactionInterface $tx
     * @param int $spendHeight
     * @return bool
     */
    public function contextualCheckInputs(TransactionInterface $tx, $spendHeight)
    {
        $math = $this->adapter->getMath();
        $hash = $tx->getTransactionId();

        $prevOuts = $this->db->coins->fetchInputs($hash, $tx->getInputs());
        $inputs = $tx->getInputs();
        if (null === $prevOuts || count($inputs) !== count($prevOuts)) {
            return false;
        }

        $valueIn = 0;
        foreach ($prevOuts as $c => $out) {
            if ($out->isCoinbase()) {
                // todo:
                if ($spendHeight - $out->getHeight() < $this->params->coinbaseMaturityAge()) {
                    return false;
                }
            }

            $valueIn = $math->add($out->getValue(), $valueIn);
            if ( !$this->params->checkAmount($valueIn) || !$this->params->checkAmount($out->getValue())) {
                return false;
            }
        }

        // todo: replace with Tx:getValueOut
        $valueOut = 0;
        $outputs = $tx->getOutputs()->getOutputs();
        foreach ($outputs as $output) {
            $valueOut = $math->add($output->getValue(), $valueOut);
            if ($this->params->checkAmount($valueOut) || $this->params->checkAmount($output->getValue())) {
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

        if (!$this->params->checkAmount($fee)) {
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
    public function checkInputs(TransactionInterface $tx, $height, Flags $flags, $checkScripts = true)
    {
        if (!$tx->isCoinbase()) {
            if (!$this->contextualCheckInputs($tx, $height)) {
                return false;
            }
        }

        $hash = $tx->getTransactionId();
        if ($checkScripts) {
            $inputs = $tx->getInputs();
            $prevOut = $this->db->coins->fetchInputs($hash, $inputs);
            $nInputs = count($inputs);
            for ($i = 0; $i < $nInputs; $i++) {
                if (is_null($prevOut)) {
                    echo "INPUTS NOT FOUND\n";
                    return false;
                }

                $factory = $this->scriptConsensus->interpreterFactory($flags);
                $interpreter = $factory->create($tx);
                $check = $interpreter->verify(
                    $tx->getInput($i)->getScript(),
                    new Script(new Buffer($prevOut[$i]->getScript())),
                    $i
                );

                if (!$check) {
                    return false;
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
        $locktime = $tx->getLockTime();
        if (0 == $locktime) {
            return true;
        }

        $math = $this->adapter->getMath();
        $basis = $math->cmp($locktime, Locktime::BLOCK_MAX) < 0 ? $height : $time;
        if ($math->cmp($locktime, $basis) < 0) {
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
            echo "no inputs..";
            return false;
        }

        $outputs = $transaction->getOutputs();
        $nOutputs = count($outputs);
        if (0 === $nOutputs) {
            echo "no outputs..";
            return false;
        }

        if ($checkSize && $transaction->getBuffer()->getSize() > $this->params->maxBlockSizeBytes()) {
            echo "max block size";
            return false;
        }

        // Check output values
        $math = $this->adapter->getMath();
        $value = 0;
        foreach ($outputs->getOutputs() as $out) {
            if ($math->cmp($out->getValue(), 0) < 0) {
                echo "txout.val err1";
                return false;
            }
            if (!$this->params->checkAmount($out->getValue())) {
                echo "txout.val err2";
                return false;
            }
            $value = $math->add($value, $out->getValue());
            if ($math->cmp($value, 0) < 0 || !$this->params->checkAmount($value)) {
                echo "txout.val err3";
                return false;
            }
        }

        // Avoid duplicate inputs
        $ins = array();
        foreach ($inputs->getInputs() as $in) {
            $ins[] = $in->getTransactionId() . $in->getVout();
        }

        $truncated = array_keys(array_flip($ins));
        if (count($truncated) !== $nInputs) {
            echo "duplicate inputs";
            return false;
        }

        unset($ins);

        $cb = $inputs->getInput(0);
        if ($nInputs == 1 && $cb->isCoinBase()) {
            $parsed = $cb->getScript()->getScriptParser()->parse();
            array_filter($parsed, function ($var) {
                return !$var instanceof Buffer;
            });
            $size = count($parsed);
            if ($size < 2 || $size > 100) {
                echo 'cb script sig wtf?';
                return false;
            }
        } else {
            foreach ($inputs->getInputs() as $input) {
                if ($input->isCoinBase()) {
                    echo 'other transaction was coinbase';
                    return false;
                }
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
            echo 'merkle woes';
            return false;
        }

        $transactions = $block->getTransactions();
        $txCount = count($transactions);
        if ($txCount == 0 || $block->getBuffer()->getSize() > $this->params->maxBlockSizeBytes()) {
            echo 'txcount or block size';
            return false;
        }

        // The first transaction is coinbase, and only the first transaction is coinbase.
        if (!$transactions->getTransaction(0)->isCoinbase()) {
            echo 'first not cb';
            return false;
        }

        for ($i = 1; $i < $txCount; $i++) {
            if ($transactions->getTransaction($i)->isCoinbase()) {
                echo 'other-than-first-cb';
                return false;
            }
        }

        for ($i = 0; $i < $txCount; $i++) {
            if (!$this->checkTransaction($transactions->getTransaction($i))) {
                return false;
            }
        }

        // todo: sigops

        return true;

    }

    /**
     * @param BlockInterface $block
     * @param Headers $headers
     * @return \BitWasp\Bitcoin\Node\BlockIndex|false
     * @throws \Exception
     */
    public function accept(BlockInterface $block, Headers $headers)
    {
        $header = $block->getHeader();

        $index = $headers->accept($header);
        if (!$index) {
            throw new \RuntimeException('Failed to accept header');
        }

        if (!$this->check($block)) {
            throw new \RuntimeException('We cannot yet deal with out of sequence blocks');
        }

        echo "inserting block\n";
        $this->db->insertBlock($index->getHeight(), $block);

        return $index;
    }

}