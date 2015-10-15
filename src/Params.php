<?php

namespace BitWasp\Bitcoin\Node;



use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionInputCollection;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Transaction\TransactionOutputCollection;
use BitWasp\Buffertools\Buffer;

class Params extends \BitWasp\Bitcoin\Chain\Params
{
    protected static $p2shActiveTime = 1333238400;

    /**
     * @var Math
     */
    private $math;

    /**
     * @param Math $math
     */
    public function __construct(Math $math)
    {
        $this->math = $math;
    }

    /**
     * @return int
     */
    public function p2shActivateTime()
    {
        return static::$p2shActiveTime;
    }

    /**
     * @return \BitWasp\Bitcoin\Block\BlockHeader
     */
    public function getGenesisBlockHeader()
    {
        return new \BitWasp\Bitcoin\Block\BlockHeader(
            '1',
            '0000000000000000000000000000000000000000000000000000000000000000',
            '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
            1231006505,
            \BitWasp\Buffertools\Buffer::hex('1d00ffff', 4, $this->math),
            2083236893
        );
    }

    /**
     * @return \BitWasp\Bitcoin\Block\BlockInterface
     */
    public function getGenesisBlock()
    {
        $timestamp = new Buffer('The Times 03/Jan/2009 Chancellor on brink of second bailout for banks', null, $this->math);
        $publicKey = Buffer::hex('04678afdb0fe5548271967f1a67130b7105cd6a828e03909a67962e0ea1f61deb649f6bc3f4cef38c4f35504e51ec112de5c384df7ba0b8d578a4c702b6bf11d5f', null, $this->math);

        $inputScript = ScriptFactory::create()
            ->push(Buffer::int('486604799', 4, $this->math))
            ->push(Buffer::int('4', null, $this->math))
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

        return new Block(
            $this->math,
            $this->getGenesisBlockHeader(),
            new TransactionCollection([$tx])
        );
    }
}