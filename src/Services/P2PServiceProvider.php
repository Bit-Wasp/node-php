<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Networking\Messages\Block;
use BitWasp\Bitcoin\Networking\Messages\GetHeaders;
use BitWasp\Bitcoin\Networking\Messages\Headers;
use BitWasp\Bitcoin\Networking\Messages\Inv;
use BitWasp\Bitcoin\Networking\Messages\Ping;
use BitWasp\Bitcoin\Networking\Peer\Locator;
use BitWasp\Bitcoin\Networking\Peer\Manager;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Node\Index\UtxoDb;
use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\P2P\Request\BlockDownloader;
use BitWasp\Bitcoin\Node\Services\P2P\State\Peers;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerStateCollection;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use Packaged\Config\ConfigProviderInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use React\EventLoop\LoopInterface;

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

        if ($this->config->getItem('config', 'download_blocks', true) && count($blocks) !== 0) {
            $blockView = $best->bestBlocksCache();
            $this->blockDownload->advertised($best, $blockView, $peer, $blocks);
        }

        if (count($vFetch) !== 0) {
            $peer->getdata($vFetch);
        }
    }

    /**
     * @param Container $container
     * @param Peer $peer
     * @param GetHeaders $getHeaders
     */
    public function onGetHeaders(Container $container, Peer $peer, GetHeaders $getHeaders)
    {
        /** @var DbInterface $db */
        $db = $container['db'];
        $chain = $this->node->chain()->getChain();

        $math = Bitcoin::getMath();
        if ($math->cmp($chain->getIndex()->getHeader()->getTimestamp(), (time() - 60 * 60 * 24)) >= 0) {
            $locator = $getHeaders->getLocator();
            if (count($locator->getHashes()) === 0) {
                $start = $locator->getHashStop();
            } else {
                $start = $db->findFork($chain, $locator);
            }

            $headers = $db->fetchNextHeaders($start);
            $peer->headers($headers);
            $container['debug']->log('peer.sentheaders', ['count' => count($headers), 'start' => $start->getHex()]);
        }
    }


    /**
     * @param Container $container
     * @param Peer $peer
     * @param Headers $headersMsg
     */
    public function onHeaders(Container $container, Peer $peer, Headers $headersMsg)
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

                if ($count >= 1999) {
                    $peer->getheaders($chainState->getHeadersLocator());
                }
            }

            if ($this->config->getItem('config', 'download_blocks', true) && $count < 2000) {
                $this->blockDownload->start($batch->getTip(), $peer);
            }

            $headers->emit('headers', [$batch]);
            $container['debug']->log('p2p.headers', ['count' => $count]);
        } catch (\Exception $e) {
            $container['debug']->log('error.onHeaders', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * @param Container $container
     * @param Peer $peer
     * @param Block $blockMsg
     */
    public function onBlock(Container $container, Peer $peer, Block $blockMsg)
    {
        $node = $this->node;
        $best = $node->chain();
        $block = $blockMsg->getBlock();

        $chainsIdx = $node->chains();
        $headerIdx = $node->headers();
        $blockIndex = $node->blocks();

        $checkSignatures = (bool) $this->config->getItem('config', 'check_signatures', true);

        try {
            $index = $blockIndex->accept($block, $headerIdx, $checkSignatures);
            unset($state);

            $chainsIdx->checkTips();
            $this->blockDownload->received($best, $peer, $index->getHash());

            $txcount = count($block->getTransactions());
            $size = number_format($block->getBuffer()->getSize() / 1024, 3) . " kb";
            $nSig = array_reduce($block->getTransactions()->all(), function ($r, TransactionInterface $v) {
                return $r + count($v->getInputs());
            }, 0);
            $this->node->emit('event', ['p2p.block', ['hash' => $index->getHash()->getHex(), 'height' => $index->getHeight(), 'size' => $size, 'nTx' => $txcount, 'nSig' => $nSig]]);
            $this->node->emit('p2p.block', [$best, $block]);
        } catch (\Exception $e) {
            $header = $block->getHeader();
            $this->node->emit('event', ['error.onBlock', ['hash' => $header->getHash()->getHex(), 'error' => $e->getMessage() . PHP_EOL . $e->getTraceAsString()]]);
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
     * @param callable $callable
     * @return \Closure
     */
    public function wrap(callable $callable)
    {
        $wrapped = array_slice(func_get_args(), 1);

        return function () use ($callable, $wrapped) {
            return call_user_func_array($callable, array_merge($wrapped, func_get_args()));
        };
    }

    /**
     * @param Peer $peer
     * @param Container $c
     * @param Manager $manager
     * @param Locator $locator
     */
    public function onPeerClose(Container $c, Manager $manager, Locator $locator, Peer $peer)
    {
        $addr = $peer->getRemoteAddr();
        $c['debug']->log('p2p.disconnect', ['peer' => ['ip' => $addr->getIp(), 'port' => $addr->getPort()]]);
        $manager->connectNextPeer($locator);
    }

    /**
     * @param Container $container
     */
    public function register(Container $container)
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

        $manager->on('outbound', function (Peer $peer) use ($container, $manager, $locator) {
            $peer->on('block', $this->wrap([$this, 'onBlock'], $container));
            $peer->on('inv', [$this, 'onInv']);
            $peer->on('headers', $this->wrap([$this, 'onHeaders'], $container));
            $peer->on('getheaders', $this->wrap([$this, 'onGetHeaders'], $container));
            $peer->on('close', $this->wrap([$this, 'onPeerClose'], $container, $manager, $locator));
            $addr = $peer->getRemoteAddr();
            $container['debug']->log('p2p.outbound', ['peer' => ['ip' => $addr->getIp(), 'port' => $addr->getPort()]]);

            $this->peersOutbound->add($peer);

            $chain = $this->node->chain();
            $height = $chain->getChain()->getIndex()->getHeight();
            $height = ($height != 0) ? $height - 1 : $height;

            $peer->getheaders($chain->getLocator($height));
        });

        $this->node->on('p2p.block', function (ChainStateInterface $chainState, BlockInterface $block) {
            if ($this->config->getItem('config', 'index_utxos', true)) {
                $utxos = $this->node->utxos();
                $utxos->update($chainState, $block);
            }
        });

        $manager->on('inbound', function (Peer $peer) use ($container) {
            $addr = $peer->getRemoteAddr();
            $container['debug']->log('p2p.inbound', ['peer' => ['ip' => $addr->getIp(), 'port' => $addr->getPort()]]);
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
