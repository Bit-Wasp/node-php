<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\CommandInterface;
use BitWasp\Bitcoin\Node\Services\UserControl\UserControl;
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
     * @param Container $c
     */
    public function register(Container $c)
    {
        $c['userControl'] = function (Container $c) {
            return new UserControl($c['zmq'], $this->node, $this->commands);
        };
    }
}
