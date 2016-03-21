<?php

namespace BitWasp\Bitcoin\Node\Services\WebSocket;


use Pimple\Container;
use BitWasp\Bitcoin\Node\NodeInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\Wamp\WampServer;
use Ratchet\WebSocket\WsServer;

class WebSocketService
{
    /**
     * @var IoServer
     */
    private $server;

    /**
     * WebSocket constructor.
     * @param NodeInterface $node
     * @param array $commands
     * @param Container $c
     */
    public function __construct(NodeInterface $node, array $commands, Container $c)
    {
        $loop = $c['loop'];
        $context = $c['zmq'];

        $pusher = new Pusher($node, $commands);

        // Listen for the web server to make a ZeroMQ push after an ajax request
        $pull = $context->getSocket(\ZMQ::SOCKET_SUB);
        $pull->connect('tcp://127.0.0.1:5566'); // Binding to 127.0.0.1 means the only client that can connect is itself
        $pull->subscribe('');
        $pull->on('message', array($pusher, 'onMessage'));

        // Set up our WebSocket server for clients wanting real-time updates
        $webSock = new \React\Socket\Server($loop);
        $webSock->listen(8080, '0.0.0.0'); // Binding to 0.0.0.0 means remotes can connect

        $this->server = new IoServer(
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

}