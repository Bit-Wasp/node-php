<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Amount;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionInputCollection;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Transaction\TransactionOutputCollection;
use BitWasp\Buffertools\Buffer;

class Params
{
    /**
     * @return int
     */
    public function maxBlockSizeBytes()
    {
        return 1000000;
    }

    /**
     * @return int
     */
    public function coinbaseMaturityAge()
    {
        return 120;
    }

    /**
     * @return int
     */
    public function maxMoney()
    {
        return 21000000;
    }

    /**
     * @return int
     */
    public function targetTimespan()
    {
        return 14 * 24 * 60 * 60;
    }

    /**
     * @return int|string
     */
    public function getPowLimit()
    {
        return '26959946667150639794667015087019630673637144422540572481103610249215';
    }

    /**
     * @return int
     */
    public function difficultyAdjustmentInterval()
    {
        return 2016;
    }

    /**
     * @param int|string $amount
     * @return bool
     */
    public function checkAmount($amount)
    {
        $math = Bitcoin::getMath();
        return $math->cmp($amount, $math->mul($this->maxMoney(), Amount::COIN)) < 0;
    }

    /**
     * @return Buffer
     */
    public function getLowestBits()
    {
        return Buffer::hex('1d00ffff', 4, Bitcoin::getMath());
    }

    /**
     * @return int
     */
    public function subsidyHalvingInterval()
    {
        return 210000;
    }

    /**
     * @return int
     */
    public function majorityWindow()
    {
        return 1000;
    }

    /**
     * @return int
     */
    public function majorityEnforceBlockUpgrade()
    {
        return 750;
    }

    /**
     * @return \BitWasp\Bitcoin\Block\BlockHeader
     */
    public function getGenesisBlockHeader(Math $math)
    {
        return new \BitWasp\Bitcoin\Block\BlockHeader(
            '1',
            '0000000000000000000000000000000000000000000000000000000000000000',
            '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
            1231006505,
            \BitWasp\Buffertools\Buffer::hex('1d00ffff', 4, $math),
            2083236893
        );
    }

    /**
     * @return \BitWasp\Bitcoin\Block\BlockInterface
     */
    public function getGenesisBlock()
    {
        $math = Bitcoin::getMath();
        $timestamp = new Buffer('The Times 03/Jan/2009 Chancellor on brink of second bailout for banks', null, $math);
        $publicKey = Buffer::hex('04678afdb0fe5548271967f1a67130b7105cd6a828e03909a67962e0ea1f61deb649f6bc3f4cef38c4f35504e51ec112de5c384df7ba0b8d578a4c702b6bf11d5f', null, $math);

        $inputScript = ScriptFactory::create()
            ->push(Buffer::int('486604799', 4, $math))
            ->push(Buffer::int('4', null, $math))
            ->push($timestamp)
            ->getScript();

        $outputScript = ScriptFactory::create()
            ->push($publicKey)
            ->op('OP_CHECKSIG')
            ->getScript();

        $tx = new Transaction(
            '1',
            new TransactionInputCollection([new TransactionInput(
                '0000000000000000000000000000000000000000000000000000000000000000',
                0xffffffff,
                $inputScript
            )]),
            new TransactionOutputCollection([new TransactionOutput(
                50,
                $outputScript
            )])
        );

        return new \BitWasp\Bitcoin\Block\Block(
            $math,
            $this->getGenesisBlockHeader($math),
            new TransactionCollection([$tx])
        );
    }
}