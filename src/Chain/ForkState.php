<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Flags;
use BitWasp\Bitcoin\Node\Db;
use BitWasp\Bitcoin\Script\Interpreter\InterpreterInterface;

class ForkState implements ForkStateInterface
{
    /**
     * @var Flags
     */
    private $flags;

    /**
     * @var bool
     */
    private $bip30 = false;

    /* Soft forks */

    /**
     * @var bool
     */
    private $bip34 = false;

    /**
     * @var bool
     */
    private $payToScriptHash = false;

    /**
     * @var bool
     */
    private $verifyDerSig = false;

    /**
     * @var bool
     */
    private $checkLocktimeVerify = false;

    /**
     * ForkState constructor.
     * @param BlockIndexInterface $index
     * @param ParamsInterface $params
     * @param Db $db
     */
    public function __construct(BlockIndexInterface $index, ParamsInterface $params, Db $db)
    {
        $math = Bitcoin::getMath();
        $header = $index->getHeader();
        $this->payToScriptHash = $math->cmp($header->getTimestamp(), $params->p2shActivateTime()) >= 0;

        $hash = $index->getHash()->getBinary();
        $this->bip30 = !(
            ($index->getHeight() == 91842 && $hash == pack("H*", "00000000000a4d0a398161ffc163c503763b1f4360639393e0e4c8e300e0caec")) ||
            ($index->getHeight() == 91880 && $hash == pack("H*", "00000000000743f190a18c5577a3c2d2a1f610ae9601ac046a38084ccb7cd721"))
        );

        $superMajorityInfo = $db->findSuperMajorityInfoByHash($index->getHash(), [2, 3, 4]);
        if ($superMajorityInfo['v2']) {
            $this->bip34 = true;
        }

        if ($superMajorityInfo['v3']) {
            $this->verifyDerSig = true;
        }

        if ($superMajorityInfo['v4']) {
            $this->checkLocktimeVerify = true;
        }

        $flags = InterpreterInterface::VERIFY_NONE;
        if ($this->payToScriptHash) {
            $flags |= InterpreterInterface::VERIFY_P2SH;
        }

        if ($math->cmp($header->getVersion(), 3) >= 0 && $this->verifyDerSig) {
            $flags |= InterpreterInterface::VERIFY_DERSIG;
        }

        if ($math->cmp($header->getVersion(), 4) >= 0 && $this->checkLocktimeVerify) {
            $flags |= InterpreterInterface::VERIFY_CHECKLOCKTIMEVERIFY;
        }

        $this->flags = new Flags($flags);
    }

    /**
     * @return bool
     */
    public function doBip30()
    {
        return $this->bip30;
    }

    /**
     * @return bool
     */
    public function doBip34()
    {
        return $this->bip34;
    }

    /**
     * @return bool
     */
    public function doPayToScriptHash()
    {
        return $this->payToScriptHash;
    }

    /**
     * @return bool
     */
    public function doDerSig()
    {
        return $this->verifyDerSig;
    }

    /**
     * @return bool
     */
    public function doCheckLocktimeVerify()
    {
        return $this->checkLocktimeVerify;
    }

    /**
     * @return Flags
     */
    public function getScriptFlags()
    {
        return $this->flags;
    }
}
