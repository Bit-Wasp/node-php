<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;


use BitWasp\Bitcoin\Networking\Message;
use BitWasp\Bitcoin\Networking\Messages\Inv;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerState;
use Evenement\EventEmitter;
use Pimple\Container;

class P2PInvService extends EventEmitter
{
    public function __construct(Container $container)
    {
        /** @var P2PService $p2p */
        $p2p = $container['p2p'];
        $p2p->on(Message::INV, [$this, 'onInv']);
    }

    /**
     * @param PeerState $state
     * @param Peer $peer
     * @param Inv $inv
     */
    public function onInv(PeerState $state, Peer $peer, Inv $inv)
    {
        $txs = [];
        $blocks = [];
        $filtered = [];
        foreach ($inv->getItems() as $item) {
            if ($item->isBlock()) {
                $blocks[] = $item;
            } elseif ($item->isTx()) {
                $txs[] = $item;
            } elseif ($item->isFilteredBlock()) {
                $filtered[] = $item;
            }
        }

        if (count($blocks) > 0) {
            $this->emit('blocks', [$state, $peer, $blocks]);
        }

        if (count($txs) > 0) {
            $this->emit('transactions', [$state, $peer, $txs]);
        }

        if (count($filtered) > 0) {
            $this->emit('filtered', [$state, $peer, $filtered]);
        }
    }
}