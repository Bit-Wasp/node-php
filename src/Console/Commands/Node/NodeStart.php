<?php

namespace BitWasp\Bitcoin\Node\Console\Commands\Node;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\Params;
use BitWasp\Bitcoin\Node\BitcoinNode;
use BitWasp\Bitcoin\Node\Console\Commands\AbstractCommand;
use BitWasp\Bitcoin\Node\HeadersNode;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NodeStart extends AbstractCommand
{
    /**
     * @var string
     */
    private $optConfig = 'config';

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('node:start')
            ->setAliases(['start'])
            ->setDescription('Start the bitcoin node')
            ->addOption($this->optConfig, 'c', InputOption::VALUE_OPTIONAL, 'Specify the location of a configuration file');
    }

    protected function isRunningAsDaemon(InputInterface $input)
    {
        if ($input->getOption('daemon') === true) {

        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $math = Bitcoin::getMath();
        $params = new Params($math);
        $loop = \React\EventLoop\Factory::create();

        // Create the child process
        // All the code after pcntl_fork () will be performed by two processes: parent and child
        if (true) {
            $child_pid = pcntl_fork();
            if ($child_pid) {
                // Exit from the parent process that is bound to the console
                exit();
            }
            // Make the child as the main process.
            posix_setsid();
        }

        $app = new BitcoinNode($params, $loop);

        $app->start();
        $loop->run();

        return 0;
    }
}
