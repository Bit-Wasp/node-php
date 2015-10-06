<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestDb extends AbstractCommand
{
    public function configure()
    {
        $this->setName('testdb');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {

    }
}