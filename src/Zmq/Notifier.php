<?php

namespace BitWasp\Bitcoin\Node\Zmq;

use BitWasp\Bitcoin\Node\BitcoinNode;
use \React\ZMQ\Context;

class Notifier
{
    /**
     * @var \React\ZMQ\SocketWrapper
     */
    private $socket;

    private $subscribe = [
        'headers.syncing',
        'blocks.syncing',
        'headers.synced',
        'blocks.synced',
        'blocks.new',
        'chain.newtip'
    ];

    /**
     * @param Context $context
     * @param BitcoinNode $node
     * @param ScriptThreadControl $threadControl
     */
    public function __construct(Context $context, BitcoinNode $node)
    {
        $this->socket = $context->getSocket(\ZMQ::SOCKET_PUB);
        $this->socket->bind('tcp://127.0.0.1:5566');

        foreach ($this->subscribe as $event) {
            $node->on($event, function (array $params = []) use ($event) {
                $this->send($event, $params);
            });
        }
    }

    /**
     * @param string $subject
     * @param array $params
     */
    public function send($subject, array $params = [])
    {
        $this->socket->send(json_encode(['event' => $subject, 'params' => $params]));
    }
}
