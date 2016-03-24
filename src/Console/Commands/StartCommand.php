<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\Params;
use BitWasp\Bitcoin\Node\BitcoinNode;
use BitWasp\Bitcoin\Node\Config\ConfigLoader;
use BitWasp\Bitcoin\Node\Db;
use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\ConfigServiceProvider;
use BitWasp\Bitcoin\Node\Services\DbServiceProvider;
use BitWasp\Bitcoin\Node\Services\Debug\ZmqDebug;
use BitWasp\Bitcoin\Node\Services\LoopServiceProvider;
use BitWasp\Bitcoin\Node\Services\P2P\P2PServiceProvider;
use BitWasp\Bitcoin\Node\Services\Retarget\RetargetServiceProvider;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\ChainsCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\CommandInterface;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\GetBlockHashCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\GetHeaderCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\GetTxCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\InfoCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\ShutdownCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\UserControlServiceProvider;
use BitWasp\Bitcoin\Node\Services\WebSocket\WebSocketServiceProvider;
use BitWasp\Bitcoin\Node\Services\ZmqServiceProvider;
use Packaged\Config\ConfigProviderInterface;
use Pimple\Container;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
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

        $db = Db::create($config);
        $node = new BitcoinNode($config, $params, $db);

        $container = new Container();
        $container['debug'] = function (Container $c) use ($node) {
            $context = $c['zmq'];
            return new ZmqDebug($node, $context);
        };

        $this->setupServices($container, $node, $loop, $config, $db);
        $loop->run();

        return 0;
    }

    /**
     * @param Container $container
     * @param NodeInterface $node
     * @param LoopInterface $loop
     * @param ConfigProviderInterface $config
     * @param DbInterface $db
     */
    public function setupServices(Container $container, NodeInterface $node, LoopInterface $loop, ConfigProviderInterface $config, DbInterface $db)
    {
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
            new LoopServiceProvider($loop),
            new ConfigServiceProvider($config),
            new DbServiceProvider($db),
            new ZmqServiceProvider(),
            new UserControlServiceProvider($node, $consoleCommands),
            new RetargetServiceProvider($node)
        ];

        $p2p = $config->getItem('config', 'p2p', true);
        if ($p2p) {
            $services[] = new P2PServiceProvider($node);
        }

        $websocket = $config->getItem('config', 'websocket', false);
        if ($websocket) {
            $services[] = new WebSocketServiceProvider($node, $basicCommands);
        }

        foreach ($services as $service) {
            $container->register($service);
        }

        // Launch services
        $container['debug'];
        $container['userControl'];
        //$container['retarget'];
        $container['p2p'];

        if ($websocket) {
            $container['websocket'];
        }

        if ($p2p) {
            $container['p2p'];
        }
    }
}
