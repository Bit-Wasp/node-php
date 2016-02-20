<?php

namespace BitWasp\Bitcoin\Node\Console\Commands\Node;

use BitWasp\Bitcoin\Node\Console\Commands\AbstractNodeClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

class NodeGetBlockHash extends AbstractNodeClient
{
    protected $description = 'Returns the block hash for the given height';

    /**
     *
     */
    protected function configure()
    {
        $this->setName('node:' . $this->getNodeCommand());
        $this->addArgument('height', InputArgument::REQUIRED, 'Block height');
        $this->setDescription($this->description);
    }

    /**
     * @param InputInterface $input
     * @return array
     */
    protected function getParams(InputInterface $input)
    {
        return [
            'height' => $input->getArgument('height')
        ];
    }

    /**
     * @return string
     */
    public function getNodeCommand()
    {
        return 'getblockhash';
    }
}
