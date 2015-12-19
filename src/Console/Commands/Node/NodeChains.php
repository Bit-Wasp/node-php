<?php

namespace BitWasp\Bitcoin\Node\Console\Commands\Node;

use BitWasp\Bitcoin\Node\Console\Commands\AbstractNodeClient;

class NodeChains extends AbstractNodeClient
{
    protected $description = 'Return information on all tracked chains';

    public function getNodeCommand()
    {
        return 'chains';
    }
}
