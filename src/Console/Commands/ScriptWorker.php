<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Node\BitcoinNode;
use BitWasp\Bitcoin\Chain\Params;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScriptWorker extends AbstractCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('scriptworker')
            ->setDescription('Start the script worker threads')
            ->addOption('count', null, InputOption::VALUE_OPTIONAL, 'Specify the number of threads');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $worker = new \BitWasp\Bitcoin\Node\Thread\ScriptWorkerThread();
        $master = new \BitWasp\Bitcoin\Node\Thread\ScriptCheckThread();

        $threads = [$master];
        for ($i = 0; $i < 16; $i++) {
            $threads[] = $worker;
        }

        new \BitWasp\Thread\Init($threads);

        return 0;
    }
}
