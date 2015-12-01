<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NodeInfo extends AbstractNodeClient
{
    protected $description = 'Get information about the running instance';

    public function getNodeCommand()
    {
        return 'info';
    }
}
