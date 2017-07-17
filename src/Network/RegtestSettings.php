<?php

namespace BitWasp\Bitcoin\Networking\Settings;

use BitWasp\Bitcoin\Networking\DnsSeeds\DnsSeedList;

class RegtestSettings extends NetworkSettings
{
    protected function setup()
    {
        $this
            ->setDefaultP2PPort(18444)
            ->setDnsSeeds(new DnsSeedList([]))
        ;
    }
}
