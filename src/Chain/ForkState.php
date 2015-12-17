<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Node\Db;

class ForkState
{

    /**
     * @var ChainState
     */
    private $chainState;

    /**
     * @var Db
     */
    private $db;
    private $index;
    private $tracked = [];
    private $payToScriptHash = false;
    private $verifyDerSig = false;
    private $checkLocktimeVerify = false;

    /**
     * ForkState constructor.
     * @param ParamsInterface $params
     * @param ChainState $state
     * @param Db $db
     * @param ForkInfoInterface[] $tracked
     */
    public function __construct(BlockIndex $index, ParamsInterface $params, Db $db, array $tracked = [])
    {
        $math = Bitcoin::getMath();
        $this->index = $index;
        $this->payToScriptHash = $math->cmp($index->getHeader()->getTimestamp(), $params->p2shActivateTime()) >= 0;

        $fetchSuperMajority = [];
        foreach ($tracked as $fork) {
            $fetchSuperMajority[] = $fork->getBlockVersion();
        }

        $results = [];
        if (count($fetchSuperMajority) > 0) {
            $results = $db->findSuperMajorityInfoByHash($index->getHash(), $fetchSuperMajority);
        }

        if (isset($results['v2']) && $results['v2']) {
            $this->bip34 = true;
        }

        if (isset($results['v3']) && $results['v3']) {
            $this->verifyDerSig = true;
        }

        if (isset($results['v4']) && $results['v4']) {
            $this->checkLocktimeVerify = true;
        }

    }

    public function doPayToScriptHash()
    {
        return $this->payToScriptHash;
    }

    public function doBip34()
    {
        return $this->bip34;
    }

    public function doDerSig()
    {
        return $this->verifyDerSig;
    }

    public function doCheckLocktimeVerify()
    {
        return $this->checkLocktimeVerify;
    }


}