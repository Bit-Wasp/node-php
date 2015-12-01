<?php

namespace BitWasp\Bitcoin\Node\Zmq;

use React\ZMQ\Context;

class ScriptThreadControl
{
    /**
     * @var \React\ZMQ\SocketWrapper
     */
    private $socket;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $threadControl = $context->getSocket(\ZMQ::SOCKET_PUB);
        $threadControl->bind('tcp://127.0.0.1:5594');
        $this->socket = $threadControl;
    }

    /**
     * @return $this
     */
    public function shutdown()
    {
        $this->socket->sendmulti(array('control', 'shutdown'));
        return $this;
    }
}
