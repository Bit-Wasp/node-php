<?php

namespace BitWasp\Bitcoin\Node\Services\UserControl;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\CommandInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class UserControlServiceProvider implements ServiceProviderInterface
{
    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * @var array
     */
    private $commands;

    /**
     * WebSocketServiceProvider constructor.
     * @param NodeInterface $node
     * @param CommandInterface[] $commands
     */
    public function __construct(NodeInterface $node, array $commands = [])
    {
        $this->node = $node;
        $this->commands = $commands;
    }

    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $container['userControl'] = function (Container $c) {
            return new UserControlService($c['zmq'], $this->node, $this->commands);
        };
    }
}
