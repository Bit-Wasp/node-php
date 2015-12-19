<?php

namespace BitWasp\Bitcoin\Node\Console\Commands\Node;

use BitWasp\Bitcoin\Node\Console\Commands\AbstractNodeClient;

class NodeInfo extends AbstractNodeClient
{
    protected $description = 'Get information about the running instance';

    public function getNodeCommand()
    {
        return 'info';
    }
}
