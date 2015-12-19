<?php
/**
 * Created by PhpStorm.
 * User: tk
 * Date: 19/12/15
 * Time: 19:49
 */
namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Flags;

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
     * @return Flags
     */
    public function getScriptFlags();
}
