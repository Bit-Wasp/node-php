<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use BitWasp\Bitcoin\Node\Console\Commands\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StopCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('stop')
            ->setDescription('Issue the stop signal');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = \React\EventLoop\Factory::create();

        $context = new \React\ZMQ\Context($loop);

        $push = $context->getSocket(\ZMQ::SOCKET_REQ);
        $push->connect('tcp://127.0.0.1:5560');
        $push->on('message', function ($message = '') use ($loop) {
            echo $message.PHP_EOL;
            $loop->stop();
        });

        $push->send(json_encode(['cmd'=>'stop']));

        $loop->run();
    }
}
