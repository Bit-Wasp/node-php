<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;


use BitWasp\Bitcoin\Node\Config\ConfigLoader;
use BitWasp\Bitcoin\Node\Db;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DbReset extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('db:reset')
            ->setDescription('Wipe everything - careful now');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = (new ConfigLoader())->load();

        $db = new Db($config);
        $db->reset();
    }
}