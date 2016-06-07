<?php

namespace BitWasp\Bitcoin\Node\Services\P2P\Request;

use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Node\Chain\ChainsInterface;
use BitWasp\Bitcoin\Node\Chain\ChainViewInterface;
use BitWasp\Bitcoin\Node\Chain\GuidedChainView;
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
     * @param ChainViewInterface $bestChain
     * @param Peer $peer
     */
    public function start(ChainViewInterface $bestChain, Peer $peer)
    {
        $this->request->requestNextBlocks($bestChain, $peer);
    }

    /**
     * @param ChainViewInterface $headerChain
     * @param GuidedChainView $blockChain
     * @param Peer $peer
     * @param array $items
     */
    public function advertised(ChainViewInterface $headerChain, GuidedChainView $blockChain, Peer $peer, array $items)
    {
        $fetch = [];
        $lastUnknown = null;
        foreach ($items as $inv) {
            $hash = $inv->getHash();
            if ($headerChain->containsHash($hash)) {
                if (!$blockChain->containsHash($hash)) {
                    $fetch[] = $inv;
                }
            } else {
                $lastUnknown = $hash;
            }
        }

        if (null !== $lastUnknown) {
            $peer->getheaders($headerChain->getHeadersLocator($lastUnknown));
            $this->peerState->fetch($peer)->updateBlockAvailability($headerChain, $lastUnknown);
        }
    }

    /**
     * @param ChainViewInterface $headerChain
     * @param Peer $peer
     * @param BufferInterface $hash
     */
    public function received(ChainViewInterface $headerChain, Peer $peer, BufferInterface $hash)
    {
        if ($this->request->isInFlight($hash)) {
            $this->request->markReceived($hash);
        }

        $this->request->requestNextBlocks($headerChain, $peer);
    }
}
