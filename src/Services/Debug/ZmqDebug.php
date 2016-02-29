<?php

namespace BitWasp\Bitcoin\Node\Services\Debug;

use React\ZMQ\Context;
use React\ZMQ\SocketWrapper;

class ZmqDebug implements DebugInterface
{
    /**
     * @var SocketWrapper
     */
    private $socket;

    /**
     * ZmqDebug constructor.
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->socket = $context->getSocket(\ZMQ::SOCKET_PUB);
        $this->socket->bind('tcp://127.0.0.1:5566');
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
