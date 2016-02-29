<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\Params;
use BitWasp\Bitcoin\Node\BitcoinNode;
use BitWasp\Bitcoin\Node\Config\ConfigLoader;
use BitWasp\Bitcoin\Node\Db;
use BitWasp\Bitcoin\Node\Services\DbServiceProvider;
use BitWasp\Bitcoin\Node\Services\Debug\ZmqDebug;
use BitWasp\Bitcoin\Node\Services\P2PServiceProvider;
use BitWasp\Bitcoin\Node\Services\UserControlServiceProvider;
use BitWasp\Bitcoin\Node\Services\WebSocketServiceProvider;
use BitWasp\Bitcoin\Node\Services\ZmqServiceProvider;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\ChainsCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\CommandInterface;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\GetBlockHashCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\GetHeaderCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\GetTxCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\InfoCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\ShutdownCommand;
use Pimple\Container;

class StartCommand extends AbstractCommand
{

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('start')
            ->setDescription('Start the bitcoin node');
    }

    /**
     * @return int
     */
    protected function execute()
    {
        $config = (new ConfigLoader())->load();

        // Create the child process
        // All the code after pcntl_fork () will be performed by two processes: parent and child
        if ($config->getItem('config', 'daemon', false)) {
            $child_pid = pcntl_fork();
            if ($child_pid) {
                // Exit from the parent process that is bound to the console
                exit();
            }
            // Make the child as the main process.
            posix_setsid();
        }

        $math = Bitcoin::getMath();
        $params = new Params($math);
        $loop = \React\EventLoop\Factory::create();

        $db = new Db($config, false);
        $node = new BitcoinNode($config, $params, $db);

        // Configure commands exposed by UserControl & WebSocket
        /** @var CommandInterface[] $basicCommands */
        $basicCommands = [
            new InfoCommand(),
            new GetTxCommand(),
            new GetHeaderCommand(),
            new GetBlockHashCommand(),
            new ChainsCommand()
        ];

        $consoleCommands = $basicCommands;
        $consoleCommands[] = new ShutdownCommand($loop);

        // Create services
        $services = [
            new DbServiceProvider($db),
            new ZmqServiceProvider($loop),
            new UserControlServiceProvider($node, $consoleCommands),
            new P2PServiceProvider($loop, $config, $node)
        ];

        $websocket = $config->getItem('config', 'websocket', false);
        if ($websocket) {
            $services[] = new WebSocketServiceProvider($loop, $node, $basicCommands);
        }

        $container = new Container();
        $container['debug'] = function (Container $c) {
            $context = $c['zmq'];
            return new ZmqDebug($context);
        };

        foreach ($services as $service) {
            $container->register($service);
        }

        // Launch services
        $container['debug'];
        $container['websocket'];
        $container['userControl'];

        $loop->run();

        return 0;
    }
}
