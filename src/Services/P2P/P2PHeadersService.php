<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;

use BitWasp\Bitcoin\Networking\Message;
use BitWasp\Bitcoin\Networking\Messages\Headers;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\Debug\DebugInterface;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerState;
use Evenement\EventEmitter;
use Pimple\Container;

class P2PHeadersService extends EventEmitter
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
     * P2PHeadersService constructor.
     * @param NodeInterface $node
     * @param Container $container
     */
    public function __construct(NodeInterface $node, Container $container)
    {
        $this->node = $node;
        $this->debug = $container['debug'];

        /** @var P2PService $p2p */
        $p2p = $container['p2p'];
        $p2p->on(Message::HEADERS, [$this, 'onHeaders']);
        $p2p->on('outbound', [$this, 'onOutboundPeer']);
    }

    /**
     * @param PeerState $state
     * @param Peer $peer
     */
    public function onOutboundPeer(PeerState $state, Peer $peer)
    {
        $chain = $this->node->chain();
        $height = $chain->getIndex()->getHeight();
        //$height = ($height != 0) ? $height - 1 : $height;

        $peer->getheaders($chain->getLocator($height));
    }
    
    /**
     * @param PeerState $state
     * @param Peer $peer
     * @param Headers $headersMsg
     */
    public function onHeaders(PeerState $state, Peer $peer, Headers $headersMsg)
    {
        $headers = $this->node->headers();

        try {
            $vHeaders = $headersMsg->getHeaders();
            echo "Processing " . count($vHeaders) . " headers\n";
            $p1 = microtime(true);
            $batch = $headers->prepareBatch($vHeaders);
            echo "Preparation: ".(microtime(true) - $p1) . " seconds\n";
            $count = count($batch->getIndices());

            if ($count > 0) {
                $p1 = microtime(true);
                $headers->applyBatch($batch);
                $view = $batch->getTip();
                $indices = $batch->getIndices();
                $indexLast = end($indices);

                $state->updateBlockAvailability($view, $indexLast->getHash());
                echo "Application: ".(microtime(true) - $p1) . " seconds\n";
                if ($count >= 1999) {
                    $peer->getheaders($view->getHeadersLocator());
                    echo "Send getheaders\n";
                }
            }

            $this->emit('headers', [$state, $peer, $batch]);
            
        } catch (\Exception $e) {
            echo "onHeaders: exception\n";
            $this->debug->log('error.onHeaders', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }
}
