<?php

namespace BitWasp\Bitcoin\Node\Services\P2P\State;

use BitWasp\Bitcoin\Networking\NetworkSerializable;
use BitWasp\Bitcoin\Networking\Peer\Peer;

class Peers
{
    /**
     * @var int
     */
    private $c = 0;

    /**
     * @var Peer[]
     */
    private $storage = [];

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param Peer $peer
     */
    public function add(Peer $peer)
    {
        $counter = $this->c++;
        $this->storage[$counter] = $peer;

        $peer->on('close', function () use ($counter) {
            unset($this->storage[$counter]);
        });
    }

    /**
     * @param NetworkSerializable $netMessage
     */
    public function push(NetworkSerializable $netMessage)
    {
        foreach ($this->storage as $peer) {
            $peer->send($netMessage);
        }
    }

    public function close()
    {
        foreach ($this->storage as $peer) {
            $peer->close();
        }
    }
}
