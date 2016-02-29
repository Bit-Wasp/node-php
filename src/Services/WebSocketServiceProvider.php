<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\WebSocket\Pusher;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\CommandInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\Wamp\WampServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\LoopInterface;

class WebSocketServiceProvider implements ServiceProviderInterface
{
    /**
     * @var LoopInterface
     */
    private $loop;

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
     * @param LoopInterface $loop
     * @param NodeInterface $node
     * @param CommandInterface[] $commands
     */
    public function __construct(LoopInterface $loop, NodeInterface $node, array $commands = [])
    {
        $this->loop = $loop;
        $this->node = $node;
        $this->commands = $commands;
    }

    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $container['websocket'] = function (Container $c) {
            $context = $c['zmq'];

            $pusher = new Pusher($this->node, $this->commands);

            // Listen for the web server to make a ZeroMQ push after an ajax request
            $pull = $context->getSocket(\ZMQ::SOCKET_SUB);
            $pull->connect('tcp://127.0.0.1:5566'); // Binding to 127.0.0.1 means the only client that can connect is itself
            $pull->subscribe('');
            $pull->on('message', array($pusher, 'onMessage'));

            // Set up our WebSocket server for clients wanting real-time updates
            $webSock = new \React\Socket\Server($this->loop);
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
        };
    }
}
