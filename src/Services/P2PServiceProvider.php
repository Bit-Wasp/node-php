<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Networking\Messages\Block;
use BitWasp\Bitcoin\Networking\Messages\GetHeaders;
use BitWasp\Bitcoin\Networking\Messages\Headers;
use BitWasp\Bitcoin\Networking\Messages\Inv;
use BitWasp\Bitcoin\Networking\Messages\Ping;
use BitWasp\Bitcoin\Networking\Peer\Locator;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Node\Services\P2P\Request\BlockDownloader;
use BitWasp\Bitcoin\Node\Services\P2P\State\Peers;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerStateCollection;
use Packaged\Config\ConfigProviderInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use React\EventLoop\LoopInterface;
use BitWasp\Bitcoin\Node\NodeInterface;

class P2PServiceProvider implements ServiceProviderInterface
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var ConfigProviderInterface
     */
    private $config;

    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * @var Peers
     */
    private $peersInbound;

    /**
     * @var Peers
     */
    private $peersOutbound;

    /**
     * @var PeerStateCollection
     */
    private $peerStates;

    /**
     * @var BlockDownloader
     */
    private $blockDownload;

    /**
     * P2PServiceProvider constructor.
     * @param LoopInterface $loop
     * @param ConfigProviderInterface $config
     * @param NodeInterface $node
     */
    public function __construct(LoopInterface $loop, ConfigProviderInterface $config, NodeInterface $node)
    {
        $this->loop = $loop;
        $this->config = $config;
        $this->node = $node;
        $this->peerStates = new PeerStateCollection();
        $this->peersInbound = new Peers();
        $this->peersOutbound = new Peers();
        $this->blockDownload = new BlockDownloader($this->node->chains(), $this->peerStates, $this->peersOutbound);

    }

    /**
     * @param Peer $peer
     * @param Inv $inv
     */
    public function onInv(Peer $peer, Inv $inv)
    {
        $best = $this->node->chain();

        $vFetch = [];
        $txs = [];
        $blocks = [];
        foreach ($inv->getItems() as $item) {
            if ($item->isBlock()) {
                $blocks[] = $item;
            } elseif ($item->isTx()) {
                $txs[] = $item;
            }
        }
        //$this->notifier->send('p2p.inv', ['blocks' => count($blocks), 'txs' => count($txs)]);

        if (count($blocks) !== 0) {
            $blockView = $best->bestBlocksCache();
            $this->blockDownload->advertised($best, $blockView, $peer, $blocks);
        }

        if (count($vFetch) !== 0) {
            $peer->getdata($vFetch);
        }
    }

    /**
     * @param DbInterface $db
     * @param Peer $peer
     * @param GetHeaders $getHeaders
     */
    public function onGetHeaders(DbInterface $db, Peer $peer, GetHeaders $getHeaders)
    {
        $chain = $this->node->chain()->getChain();

        $math = Bitcoin::getMath();
        if ($math->cmp($chain->getIndex()->getHeader()->getTimestamp(), (time() - 60*60*24)) >= 0) {
            $locator = $getHeaders->getLocator();
            if (count($locator->getHashes()) === 0) {
                $start = $locator->getHashStop();
            } else {
                $start = $db->findFork($chain, $locator);
            }

            $headers = $db->fetchNextHeaders($start);
            $peer->headers($headers);
            //$this->notifier->send('peer.sentheaders', ['count' => count($headers), 'start'=>$start->getHex()]);
        }
    }


    /**
     * @param Peer $peer
     * @param Headers $headersMsg
     */
    public function onHeaders(Peer $peer, Headers $headersMsg)
    {
        $chains = $this->node->chains();
        $headers = $this->node->headers();

        try {
            $vHeaders = $headersMsg->getHeaders();
            $batch = $headers->prepareBatch($vHeaders);
            $count = count($batch->getIndices());

            if ($count > 0) {
                $headers->applyBatch($batch);
                $chains->checkTips();
                $chainState = $batch->getTip();
                $indexLast = end($batch->getIndices());

                $this->peerStates->fetch($peer)->updateBlockAvailability($chainState, $indexLast->getHash());

                if ($count === 2000) {
                    $peer->getheaders($chainState->getHeadersLocator());
                }
            }

            if ($count < 2000) {
                $this->blockDownload->start($batch->getTip(), $peer);
            }

            //$this->notifier->send('p2p.headers', ['count' => $count]);

        } catch (\Exception $e) {
            //$this->notifier->send('error.onHeaders', ['error' => $e->getMessage()]);
            echo $e->getMessage() . PHP_EOL;
            echo $e->getTraceAsString().PHP_EOL;
            die();
        }

    }


    /**
     * @param Peer $peer
     * @param Block $blockMsg
     */
    public function onBlock(Peer $peer, Block $blockMsg)
    {
        $node = $this->node;
        $best = $node->chain();
        $block = $blockMsg->getBlock();

        $chainsIdx = $node->chains();
        $headerIdx = $node->headers();
        $blockIndex = $node->blocks();

        try {

            $index = $blockIndex->accept($block, $headerIdx);
            unset($state);
            //$this->notifier->send('p2p.block', ['hash' => $index->getHash()->getHex(), 'height' => $index->getHeight()]);

            $chainsIdx->checkTips();
            $this->blockDownload->received($best, $peer, $index->getHash());

        } catch (\Exception $e) {
            $header = $block->getHeader();
            //$this->notifier->send('error.onBlock', ['hash'=>$header->getHash()->getHex(),'error' => $e->getMessage()]);
            echo 'Failed to accept block' . PHP_EOL;

            echo $e->getMessage() . PHP_EOL;

            if ($best->getChain()->containsHash($block->getHeader()->getPrevBlock())) {
                if ($header->getPrevBlock() === $best->getLastBlock()->getHash()) {
                    echo $block->getHeader()->getHash()->getHex() . PHP_EOL;
                    echo $block->getHex() . PHP_EOL;
                    echo 'We have prevblockIndex, so this is weird.';
                    echo $e->getTraceAsString() . PHP_EOL;
                    echo $e->getMessage() . PHP_EOL;
                } else {
                    echo 'Didn\'t elongate the chain, probably from the future..' . PHP_EOL;
                }
            }

            echo $e->getTraceAsString() . PHP_EOL;
            die();
        }
    }

    /**
     * @param Peer $peer
     * @param Ping $ping
     */
    public function onPing(Peer $peer, Ping $ping)
    {
        $peer->pong($ping);
    }

    /**
     * @param Container $c
     */
    public function register(Container $c)
    {
        $txRelay = $this->config->getItem('config', 'tx_relay', false);
        $netFactory = new \BitWasp\Bitcoin\Networking\Factory($this->loop, Bitcoin::getNetwork());

        $dns = $netFactory->getDns();
        $peerFactory = $netFactory->getPeerFactory($dns);
        $handler = $peerFactory->getPacketHandler();
        $handler->on('ping', array($this, 'onPing'));

        $locator = $peerFactory->getLocator();
        $manager = $peerFactory->getManager($txRelay);
        $manager->registerHandler($handler);

        // Setup listener if required
        if ($this->config->getItem('config', 'listen', '0')) {
            $listener = $peerFactory->getListener(new \React\Socket\Server($this->loop));
            $manager->registerListener($listener);
        }

        $manager->on('outbound', function (Peer $peer) use ($c) {
            $peer->on('block', array ($this, 'onBlock'));
            $peer->on('inv', array ($this, 'onInv'));
            $peer->on('headers', array ($this, 'onHeaders'));
            $peer->on('getheaders', function (Peer $peer, GetHeaders $getheaders) use ($c) {
                $this->onGetHeaders($c['db'], $peer, $getheaders);
            });

            //$addr = $peer->getRemoteAddr();
            /*$this->notifier->send('peer.outbound.new', ['peer' =>[
                'ip' => $addr->getIp(),
                'port' => $addr->getPort()
            ]]);*/

            $this->peersOutbound->add($peer);

            $chain = $this->node->chain();
            $height = $chain->getChain()->getIndex()->getHeight();
            $height = ($height != 0) ? $height - 1 : $height;

            $peer->getheaders($chain->getLocator($height));
        });

        $manager->on('inbound', function (Peer $peer) {
            /*$this->notifier->send('peer.inbound.new', ['peer' =>[
                'ip' => $peer->getRemoteAddr()->getIp(),
                'port' => $peer->getRemoteAddr()->getPort()
            ]]);*/
            $this->peersInbound->add($peer);
        });

        $locator
            ->queryDnsSeeds(1)
            ->then(function (Locator $locator) use ($manager, $handler) {
                for ($i = 0; $i < 1; $i++) {
                    $manager->connectNextPeer($locator);
                }
            });
    }
}
