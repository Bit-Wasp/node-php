<?php

namespace BitWasp\Bitcoin\Node\Services\P2P\Ping;


use BitWasp\Bitcoin\Networking\Message;
use BitWasp\Bitcoin\Networking\Messages\Ping;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Node\Services\P2P\MiniP2PService;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerState;
use Evenement\EventEmitter;
use Pimple\Container;

class P2PPingService extends EventEmitter
{
    public function __construct(Container $container)
    {
        /** @var MiniP2PService $p2p */
        $p2p = $container['p2p'];
        $p2p->on(Message::PING, [$this, 'onPing']);
    }

    /**
     * @param PeerState $state
     * @param Peer $peer
     * @param Ping $ping
     */
    public function onPing(PeerState $state, Peer $peer, Ping $ping)
    {
        $peer->pong($ping);
    }
}