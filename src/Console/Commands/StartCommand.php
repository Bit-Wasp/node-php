<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\Params;
use BitWasp\Bitcoin\Node\BitcoinNode;
use BitWasp\Bitcoin\Node\Config\ConfigLoader;
use BitWasp\Bitcoin\Node\WebSocket\Pusher;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\ChainsCommand;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\CommandInterface;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\GetBlockHashCommand;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\GetHeaderCommand;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\GetTxCommand;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\InfoCommand;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\ShutdownCommand;
use BitWasp\Bitcoin\Node\Zmq\UserControl;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\Wamp\WampServer;
use Ratchet\WebSocket\WsServer;
use React\ZMQ\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends AbstractCommand
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
        $config = (new ConfigLoader())->load();

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

        $context = new Context($loop);
        $app = new BitcoinNode($config, $context, $params, $loop);

        // Activate ZMQ services

        /** @var CommandInterface[] $commands */
        $commands = [
            new InfoCommand(),
            new GetTxCommand(),
            new GetHeaderCommand(),
            new GetBlockHashCommand(),
            new ChainsCommand()
        ];

        $consoleCommands = $commands;
        $consoleCommands[] = new ShutdownCommand($context);

        $control = new UserControl($context, $app, $consoleCommands);

        $websocket = $config->getItem('config', 'websocket', false);

        if ($websocket) {
            $pusher = new Pusher($app, $commands);

            // Listen for the web server to make a ZeroMQ push after an ajax request
            $pull = $context->getSocket(\ZMQ::SOCKET_SUB);
            $pull->connect('tcp://127.0.0.1:5566'); // Binding to 127.0.0.1 means the only client that can connect is itself
            $pull->subscribe('');
            $pull->on('message', array($pusher, 'onMessage'));

            // Set up our WebSocket server for clients wanting real-time updates
            $webSock = new \React\Socket\Server($loop);
            $webSock->listen(8080, '0.0.0.0'); // Binding to 0.0.0.0 means remotes can connect

            new IoServer(
                new HttpServer(
                    new WsServer(
                        new WampServer(
                            $pusher
                        )
                    )
                ),
                $webSock
            );
        }

        $app->start();
        $loop->run();

        return 0;
    }
}
