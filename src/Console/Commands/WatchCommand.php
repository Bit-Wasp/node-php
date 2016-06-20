<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use React\ZMQ\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WatchCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('watch')
            ->setDescription('Watch for messages from the bitcoin node')
            ->addOption('all', 'a', InputOption::VALUE_OPTIONAL, 'Display all messages', false)
            ->addOption('events', 'e', InputOption::VALUE_OPTIONAL, 'Comma separated list of messages to filter', false)
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $all = $input->getOption('all');
        $events = $input->getOption('events');

        $filter = [];
        if (!$all) {
            $filter = explode(",", $events);
        }

        $loop = \React\EventLoop\Factory::create();
        $context = new Context($loop);
        $cmdControl = $context->getSocket(\ZMQ::SOCKET_SUB);
        $cmdControl->connect('tcp://127.0.0.1:5566');
        $cmdControl->subscribe('');

        $cmdControl->on('message', function ($e) use ($filter) {
            if (!empty($filter)) {
                $parsed = json_decode($e, true);
                if (!in_array($parsed['event'], $filter)) {
                    return;
                }
            }

            echo $e . PHP_EOL;
        });
        $loop->run();

        return 0;
    }
}
