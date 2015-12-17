<?php

namespace BitWasp\Bitcoin\Node\Chain;


interface ForkInfoInterface
{

    public function getBlockVersion();
    public function countMajorityEnforceUpgrade();
}