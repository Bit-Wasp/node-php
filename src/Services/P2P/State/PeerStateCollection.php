<?php

namespace BitWasp\Bitcoin\Node\Services\P2P\State;

use BitWasp\Bitcoin\Networking\Peer\Peer;

class PeerStateCollection
{
    /**
     * @var \SplObjectStorage
     */
    private $storage;

    public function __construct()
    {
        $this->storage = new \SplObjectStorage();
    }

    /**
     * @return \SplObjectStorage
     */
    public function storage()
    {
        return $this->storage;
    }

    /**
     * @param Peer $peer
     * @return PeerState
     */
    public function fetch(Peer $peer)
    {
        if (!$this->storage->contains($peer)) {
            $state = $this->createState($peer);
        } else {
            $state = $this->storage->offsetGet($peer);
        }

        return $state;
    }

    /**
     * @param Peer $peer
     * @return PeerState
     */
    public function createState(Peer $peer)
    {
        $peerState = PeerState::create();
        $this->storage->attach($peer, $peerState);
        return $peerState;
    }
}
