<?php

namespace BitWasp\Bitcoin\Node\Params;

use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Chain\Params;
use BitWasp\Bitcoin\Collection\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Script\Opcodes;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Buffertools\Buffer;

class RegtestParams extends Params
{
    /**
     * @var int
     */
    protected static $maxBlockSizeBytes = 1000000;

    /**
     * @var int
     */
    protected static $maxMoney = 21000000;

    /**
     * @var int
     */
    protected static $subsidyHalvingInterval = 150;

    /**
     * @var int
     */
    protected static $coinbaseMaturityAge = 120;

    /**
     * @var int
     */
    protected static $p2shActivateTime = 1333238400;

    /**
     * = 14 * 24 * 60 * 60
     * @var int
     */
    protected static $powTargetTimespan = 1209600;

    /**
     * = 10 * 60
     * @var int
     */
    protected static $powTargetSpacing = 600;

    /**
     * @var int
     */
    protected static $powRetargetInterval = 2016;

    /**
     * @var string
     */
    protected static $powTargetLimit = '57896044618658097711785492504343953926634992332820282019728792003956564819967';

    /**
     * Hex: 1d00ffff
     * @var string
     */
    protected static $powBitsLimit = 0x207fffff;

    /**
     * @var int
     */
    protected static $majorityWindow = 1000;

    /**
     * @var int
     */
    protected static $majorityEnforceBlockUpgrade = 750;

    /**
     * @return \BitWasp\Bitcoin\Block\BlockHeaderInterface
     */
    public function getGenesisBlockHeader()
    {
        return new BlockHeader(
            '1',
            Buffer::hex('00', 32),
            Buffer::hex('4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b', 32),
            '1296688602',
            Buffer::hex('207fffff', 4, $this->math),
            '2'
        );
    }

    /**
     * @return \BitWasp\Bitcoin\Block\BlockInterface
     */
    public function getGenesisBlock()
    {
        $timestamp = new Buffer('The Times 03/Jan/2009 Chancellor on brink of second bailout for banks', null, $this->math);
        $publicKey = Buffer::hex('04678afdb0fe5548271967f1a67130b7105cd6a828e03909a67962e0ea1f61deb649f6bc3f4cef38c4f35504e51ec112de5c384df7ba0b8d578a4c702b6bf11d5f', null, $this->math);

        $inputScript = ScriptFactory::sequence([
            Buffer::int('486604799', 4, $this->math)->flip(),
            Buffer::int('4', null, $this->math),
            $timestamp
        ]);

        $outputScript = ScriptFactory::sequence([$publicKey, Opcodes::OP_CHECKSIG]);

        return new Block(
            $this->math,
            $this->getGenesisBlockHeader(),
            new TransactionCollection([
                (new TxBuilder())
                    ->version('1')
                    ->input(new Buffer('', 32), 0xffffffff, $inputScript)
                    ->output(5000000000, $outputScript)
                    ->locktime(0)
                    ->get()
            ])
        );
    }
}
