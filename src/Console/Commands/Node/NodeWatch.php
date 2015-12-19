<?php

namespace BitWasp\Bitcoin\Node\Console\Commands\Node;

use BitWasp\Bitcoin\Node\Console\Commands\AbstractCommand;
use React\ZMQ\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NodeWatch extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('node:watch')
            ->setDescription('Watch for messages from the bitcoin node');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = \React\EventLoop\Factory::create();
        $context = new Context($loop);
        $cmdControl = $context->getSocket(\ZMQ::SOCKET_SUB);
        $cmdControl->connect('tcp://127.0.0.1:5566');
        $cmdControl->subscribe('');
        $cmdControl->on('message', function ($e) {
            echo $e . PHP_EOL;
        });
        $loop->run();

        return 0;
    }
}
