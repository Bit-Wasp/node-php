<?php

namespace BitWasp\Bitcoin\Node\Console\Commands\Db;

use BitWasp\Bitcoin\Node\Config\ConfigLoader;
use BitWasp\Bitcoin\Node\Console\Commands\AbstractCommand;
use BitWasp\Bitcoin\Node\Db;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DbWipe extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('db:wipe')
            ->setDescription('Wipe EVERYTHING!');
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
        $db->wipe();
    }
}