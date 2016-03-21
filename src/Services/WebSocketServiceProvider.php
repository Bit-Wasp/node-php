<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\CommandInterface;
use BitWasp\Bitcoin\Node\Services\WebSocket\WebSocketService;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class WebSocketServiceProvider implements ServiceProviderInterface
{

    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * @var array
     */
    private $commands = [];

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
        $container['websocket'] = function (Container $container) {
            return new WebSocketService($this->node, $this->commands, $container);
        };
    }
}
