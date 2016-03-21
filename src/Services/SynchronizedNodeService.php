<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\NodeInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use React\ZMQ\SocketWrapper;

class SynchronizedNodeService implements ServiceProviderInterface
{
    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * @var SocketWrapper
     */
    private $socket;

    /**
     * SynchronizedNodeService constructor.
     * @param NodeInterface $node
     */
    public function __construct(NodeInterface $node)
    {
        $this->node = $node;
    }

    /**
     * @param ChainStateInterface $tip
     */
    public function onNewTip(ChainStateInterface $tip)
    {
        $index = $tip->getIndex();
        $header = $index->getHeader();
        $this->socket->send(json_encode([
            'height' => $index->getHeight(),
            'hash' => $index->getHash()->getHex(),
            'work' => $index->getWork(),
            'header' => [
                'version' => $header->getVersion(),
                'merkleRoot' => $header->getMerkleRoot()->getHex(),
                'prevBlock' => $header->getPrevBlock()->getHex(),
                'nBits' => $header->getBits()->getInt(),
                'nTimestamp' => $header->getTimestamp(),
                'nNonce' => $header->getNonce()
            ]
        ]));
    }

    /**
     * @param Container $pimple
     */
    public function register(Container $pimple)
    {
        /** @var \React\ZMQ\Context $zmq */
        $zmq = $pimple['zmq'];
        $this->socket = $zmq->getSocket(\ZMQ::SOCKET_PUB);
        $this->socket->bind('tcp://127.0.0.1:5522');

        $chains = $this->node->chains();
        $chains->on('newtip', [$this, 'onNewTip']);
    }
}
