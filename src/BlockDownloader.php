<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Networking\Structure\Inventory;
use BitWasp\Bitcoin\Node\Chain\ChainCache;
use BitWasp\Bitcoin\Node\Chain\Chains;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\State\Peers;
use BitWasp\Bitcoin\Node\State\PeerStateCollection;
use BitWasp\Bitcoin\Node\Thread\BlockRequest;

class BlockDownloader
{

    /**
     * @var Chains
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
     * @var string[]
     */
    private $blocksInFlight = [];

    /**
     * @param PeerStateCollection $peerStates
     * @param Chains $chains
     * @param Peers $outboundPeers
     */
    public function __construct(Chains $chains, PeerStateCollection $peerStates, Peers $outboundPeers)
    {
        $this->chains = $chains;
        $this->outboundPeers = $outboundPeers;
        $this->peerState = $peerStates;
        $this->request = new BlockRequest();
    }

    /**
     * @param ChainState $bestChain
     * @param Peer $peer
     */
    public function start(ChainState $bestChain, Peer $peer)
    {
        $this->request->requestNextBlocks($bestChain, $peer);
    }

    /**
     * @param ChainState $state
     * @param ChainCache $chainView
     * @param Peer $peer
     * @param Inventory[] $items
     */
    public function advertised(ChainState $state, ChainCache $chainView, Peer $peer, array $items)
    {
        $chain = $state->getChain();
        $fetch = [];
        $lastUnknown = null;
        foreach ($items as $inv) {
            $hash = $inv->getHash()->getHex();
            if ($chain->containsHash($hash) ){
                if (!$chainView->containsHash($hash)) {
                    $fetch[] = $inv;
                }
            } else {
                $lastUnknown = $inv->getHash();
            }
        }

        if (!is_null($lastUnknown)) {
            $peer->getheaders($state->getHeadersLocator($lastUnknown));
        }

        if (count($fetch) > 0) {
            echo "SEND GETDATA:" . count($fetch) . "\n";
            $peer->getdata($fetch);
        }

    }

    /**
     * @param ChainState $bestChain
     * @param Peer $peer
     * @param string $hash
     */
    public function received(ChainState $bestChain, Peer $peer, $hash)
    {
        if ($this->request->isInFlight($hash)) {
            $this->request->markReceived($hash);
        }

        $this->request->requestNextBlocks($bestChain, $peer);
    }
}