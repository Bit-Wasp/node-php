<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use React\ZMQ\Context;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BlockVelocityCommand extends AbstractCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('block:velocity')
            ->addArgument('interval', InputArgument::OPTIONAL, 'Time between output', 5)
            ->setDescription('Start the watch websocket');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = \React\EventLoop\Factory::create();

        // Listen for the web server to make a ZeroMQ push after an ajax request
        $context = new Context($loop);
        $pull = $context->getSocket(\ZMQ::SOCKET_SUB);
        $pull->connect('tcp://127.0.0.1:5566'); // Binding to 127.0.0.1 means the only client that can connect is itself
        $pull->subscribe('');

        $batch = 0;

        $pull->on('message', function ($msg) use (&$batch) {
            $arr = json_decode($msg);
            if ($arr->event == 'block') {
                $batch++;
            }
        });

        $period = $input->getArgument('interval');
        $loop->addPeriodicTimer($period, function () use (&$batch, $period) {
            $velocity = $batch / $period;
            echo " count: " . $batch . " - or " . $velocity . " per second " . PHP_EOL;
            $batch = 0;
        });

        $loop->run();

        return 0;
    }
}
