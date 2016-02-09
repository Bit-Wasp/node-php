<?php

namespace BitWasp\Bitcoin\Node\Chain;

interface ForkStateInterface
{
    /**
     * @return bool
     */
    public function doBip30();

    /**
     * @return bool
     */
    public function doBip34();

    /**
     * @return bool
     */
    public function doPayToScriptHash();

    /**
     * @return bool
     */
    public function doDerSig();

    /**
     * @return bool
     */
    public function doCheckLocktimeVerify();

    /**
     * @return int
     */
    public function getScriptFlags();
}
