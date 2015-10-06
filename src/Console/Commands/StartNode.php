<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;



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
        $params = new \BitWasp\Bitcoin\Node\Params();
        $loop = \React\EventLoop\Factory::create();
        $app = new \BitWasp\Bitcoin\Node\BitcoinNode($params, $loop);

        $context = new \React\ZMQ\Context($loop);

        $pull = $context->getSocket(\ZMQ::SOCKET_PULL);
        $pull->bind('tcp://127.0.0.1:5560');
        $pull->on('message', function ($e) use ($app, $loop) {
            if ($e == 'shutdown') {
                $loop->stop();
                $a = microtime(true);
                $app->stop();
                echo "Takes " . (microtime(true) - $a) . " to shutdown\n";
            }
            echo "RECEIVED MESSAGE: $e\n";
        });

        $app->start();
        $loop->run();

        return 0;
    }
}