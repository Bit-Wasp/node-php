<?php

namespace BitWasp\Bitcoin\Node\Services\P2P\Request;

use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Networking\Structure\Inventory;
use BitWasp\Bitcoin\Node\Chain\ChainCacheInterface;
use BitWasp\Bitcoin\Node\Chain\ChainsInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Services\P2P\State\Peers;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerStateCollection;
use BitWasp\Buffertools\BufferInterface;

class BlockDownloader
{

    /**
     * @var ChainsInterface
     */
    private $chains;

    /**
     * @var Peers
     */
    private $outboundPeers;

    /**
     * @var PeerStateCollection
     */
    private $peerState;

    /**
     * @param PeerStateCollection $peerStates
     * @param ChainsInterface $chains
     * @param Peers $outboundPeers
     */
    public function __construct(ChainsInterface $chains, PeerStateCollection $peerStates, Peers $outboundPeers)
    {
        $this->chains = $chains;
        $this->outboundPeers = $outboundPeers;
        $this->peerState = $peerStates;
        $this->request = new BlockRequest();
    }

    /**
     * @param ChainStateInterface $bestChain
     * @param Peer $peer
     */
    public function start(ChainStateInterface $bestChain, Peer $peer)
    {
        $this->request->requestNextBlocks($bestChain, $peer);
    }

    /**
     * @param ChainStateInterface $chain
     * @param ChainCacheInterface $chainView
     * @param Peer $peer
     * @param Inventory[] $items
     */
    public function advertised(ChainStateInterface $chain, ChainCacheInterface $chainView, Peer $peer, array $items)
    {
        $fetch = [];
        $lastUnknown = null;
        foreach ($items as $inv) {
            $hash = $inv->getHash();
            if ($chain->containsHash($hash)) {
                if (!$chainView->containsHash($hash)) {
                    $fetch[] = $inv;
                }
            } else {
                $lastUnknown = $hash;
            }
        }

        if (null !== $lastUnknown) {
            $peer->getheaders($chain->getHeadersLocator($lastUnknown));
            $this->peerState->fetch($peer)->updateBlockAvailability($chain, $lastUnknown);
        }

        if (count($fetch) > 0) {
            $peer->getdata($fetch);
        }

    }

    /**
     * @param ChainStateInterface $bestChain
     * @param Peer $peer
     * @param BufferInterface $hash
     */
    public function received(ChainStateInterface $bestChain, Peer $peer, BufferInterface $hash)
    {
        if ($this->request->isInFlight($hash)) {
            $this->request->markReceived($hash);
        }

        $this->request->requestNextBlocks($bestChain, $peer);
    }
}
