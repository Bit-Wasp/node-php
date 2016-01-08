<?php

namespace BitWasp\Bitcoin\Node\Console\Commands\Node;

use BitWasp\Bitcoin\Node\Console\Commands\AbstractNodeClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

class NodeTx extends AbstractNodeClient
{
    protected $description = 'Get information about a transaction';

    /**
     *
     */
    protected function configure()
    {
        $this->setName('node:' . $this->getNodeCommand());
        $this->addArgument('txid', InputArgument::REQUIRED, 'Transaction hash');
        if (null !== $this->description) {
            $this->setDescription($this->description);
        }
    }

    protected function getParams(InputInterface $input)
    {
        return [
            'txid' => $input->getArgument('txid')
        ];
    }

    public function getNodeCommand()
    {
        return 'tx';
    }
}
