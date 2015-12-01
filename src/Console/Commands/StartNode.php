<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Node\BitcoinNode;
use BitWasp\Bitcoin\Chain\Params;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartNode extends AbstractCommand
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
            ->setName('start')
            ->setDescription('Start the bitcoin node')
            ->addOption($this->optConfig, 'c', InputOption::VALUE_OPTIONAL, 'Specify the location of a configuration file');
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
        $app = new BitcoinNode($params, $loop);

        $app->start();
        $loop->run();

        return 0;
    }
}
