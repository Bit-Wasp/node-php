<?php

namespace BitWasp\Bitcoin\Node\Console\Commands\Node;

use BitWasp\Bitcoin\Node\Console\Commands\AbstractNodeClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

class NodeGetHeader extends AbstractNodeClient
{
    protected $description = 'Get information about a block header';

    /**
     *
     */
    protected function configure()
    {
        $this->setName('node:' . $this->getNodeCommand());
        $this->addArgument('hash', InputArgument::REQUIRED, 'Block hash');
        $this->setDescription($this->description);
    }

    protected function getParams(InputInterface $input)
    {
        return [
            'hash' => $input->getArgument('hash')
        ];
    }

    public function getNodeCommand()
    {
        return 'getheader';
    }
}
