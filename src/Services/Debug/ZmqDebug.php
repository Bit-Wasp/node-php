<?php

namespace BitWasp\Bitcoin\Node\Services\Debug;

use React\ZMQ\Context;
use React\ZMQ\SocketWrapper;
use BitWasp\Bitcoin\Node\NodeInterface;

class ZmqDebug implements DebugInterface
{
    /**
     * @var SocketWrapper
     */
    private $socket;

    /**
     * ZmqDebug constructor.
     * @param NodeInterface $node
     * @param Context $context
     */
    public function __construct(NodeInterface $node, Context $context)
    {
        $this->socket = $context->getSocket(\ZMQ::SOCKET_PUB);
        $this->socket->bind('tcp://127.0.0.1:5566');
        $node->on('event', function ($event, array $params) {
            $this->log($event, $params);
        });
    }

    /**
     * @param string $subject
     * @param array $params
     */
    public function log($subject, array $params = [])
    {
        $this->socket->send(json_encode(['event' => $subject, 'params' => $params]));
    }
}
