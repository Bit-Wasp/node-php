<?php

namespace BitWasp\Bitcoin\Node\Services\Debug;

use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
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

        $node->chains()->on('newtip', function (ChainStateInterface $tip) {
            $index = $tip->getChainIndex();
            $this->log('chain.newtip', ['hash' => $index->getHash()->getHex(), 'height' => $index->getHeight(), 'work'=> $index->getWork()]);
        });

        $node->chains()->on('retarget', function (ChainStateInterface $tip) {
            $index = $tip->getChainIndex();
            $this->log('chain.retarget', ['hash' => $index->getHash()->getHex(), 'height' => $index->getHeight()]);
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
