<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Networking\Message;
use BitWasp\Bitcoin\Networking\Messages\GetHeaders;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\Debug\DebugInterface;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerState;
use Evenement\EventEmitter;
use Pimple\Container;

class P2PGetHeadersService extends EventEmitter
{
    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * @var DebugInterface
     */
    private $debug;

    /**
     * @var DbInterface
     */
    private $db;

    /**
     * P2PGetHeadersService constructor.
     * @param NodeInterface $node
     * @param Container $container
     */
    public function __construct(NodeInterface $node, Container $container)
    {
        $this->node = $node;
        $this->db = $container['db'];
        $this->debug = $container['debug'];
        
        /** @var P2PService $p2p */
        $p2p = $container['p2p'];
        $p2p->on(Message::GETHEADERS, [$this, 'onGetHeaders']);
    }

    /**
     * @param PeerState $state
     * @param Peer $peer
     * @param GetHeaders $getHeaders
     */
    public function onGetHeaders(PeerState $state, Peer $peer, GetHeaders $getHeaders)
    {
        $chain = $this->node->chain();

        $math = Bitcoin::getMath();
        if ($math->cmp($chain->getIndex()->getHeader()->getTimestamp(), (time() - 60 * 60 * 24)) >= 0) {
            $locator = $getHeaders->getLocator();
            if (count($locator->getHashes()) === 0) {
                $start = $locator->getHashStop();
            } else {
                $start = $this->db->findFork($chain, $locator);
            }

            $headers = $this->db->fetchNextHeaders($start);
            $peer->headers($headers);
            $this->debug->log('peer.sentheaders', ['count' => count($headers), 'start' => $start->getHex()]);
        }
    }
}
