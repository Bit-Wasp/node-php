<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Networking\Message;
use BitWasp\Bitcoin\Networking\Messages\Addr;
use BitWasp\Bitcoin\Networking\Messages\Block;
use BitWasp\Bitcoin\Networking\Messages\GetHeaders;
use BitWasp\Bitcoin\Networking\Messages\Headers;
use BitWasp\Bitcoin\Networking\Messages\Inv;
use BitWasp\Bitcoin\Networking\Messages\Ping;
use BitWasp\Bitcoin\Networking\Peer\ConnectionParams;
use BitWasp\Bitcoin\Networking\Peer\Connector;
use BitWasp\Bitcoin\Networking\Peer\Listener;
use BitWasp\Bitcoin\Networking\Peer\Locator;
use BitWasp\Bitcoin\Networking\Peer\Manager;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Networking\Protocol;
use BitWasp\Bitcoin\Networking\Services;
use BitWasp\Bitcoin\Networking\Structure\NetworkAddressInterface;
use BitWasp\Bitcoin\Node\Chain\BlockData;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\P2P\Request\BlockDownloader;
use BitWasp\Bitcoin\Node\Services\P2P\State\Peers;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerStateCollection;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use Packaged\Config\ConfigProviderInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Socket\Server;

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
     * @var \BitWasp\Bitcoin\Networking\Factory
     */
    private $factory;

    /**
     * @var ConnectionParams
     */
    private $params;

    /**
     * @var Connector
     */
    private $connector;

    /**
     * @var \BitWasp\Bitcoin\Networking\Messages\Factory
     */
    private $messages;

    /**
     * @var Manager
     */
    private $manager;

    /**
     * @var Locator
     */
    private $locator;

    /**
     * @var Container
     */
    private $container;

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
        $this->factory = new \BitWasp\Bitcoin\Networking\Factory($this->loop, Bitcoin::getNetwork());
        $dns = $this->factory->getDns();
        $this->messages = $this->factory->getMessages();

        $this->params = new ConnectionParams();
        $this->params->requestTxRelay((bool) $this->config->getItem('config', 'tx_relay', false));

        $this->connector = new Connector($this->messages, $this->params, $this->loop, $dns);
        $this->manager = new Manager($this->connector);
        $this->locator = new Locator($dns);

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
     * @param Peer $peer
     * @param GetHeaders $getHeaders
     */
    public function onGetHeaders(Peer $peer, GetHeaders $getHeaders)
    {
        /** @var DbInterface $db */
        $db = $this->container['db'];
        $chain = $this->node->chain();

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
            $this->container['debug']->log('peer.sentheaders', ['count' => count($headers), 'start' => $start->getHex()]);
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
                $indices = $batch->getIndices();
                $indexLast = end($indices);

                $this->peerStates->fetch($peer)->updateBlockAvailability($chainState, $indexLast->getHash());

                if ($count >= 1999) {
                    $peer->getheaders($chainState->getHeadersLocator());
                }
            }

            if ($this->config->getItem('config', 'download_blocks', true) && $count < 2000) {
                $this->blockDownload->start($batch->getTip(), $peer);
            }

            $this->container['debug']->log('p2p.headers', ['ip' => $peer->getRemoteAddress()->getIp(), 'count' => $count]);
        } catch (\Exception $e) {
            $this->container['debug']->log('error.onHeaders', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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

        $checkSignatures = (bool) $this->config->getItem('config', 'check_signatures', true);
        $checkSize = (bool) $this->config->getItem('config', 'check_block_size', true);
        $checkMerkleRoot = (bool) $this->config->getItem('config', 'check_merkle_root', true);

        try {
            $index = $blockIndex->accept($block, $headerIdx, $checkSignatures, $checkSize, $checkMerkleRoot);

            $chainsIdx->checkTips();
            $this->blockDownload->received($best, $peer, $index->getHash());

            $txcount = count($block->getTransactions());
            $nSig = array_reduce($block->getTransactions()->all(), function ($r, TransactionInterface $v) {
                return $r + count($v->getInputs());
            }, 0);
            $this->node->emit('event', ['p2p.block', ['ip' => $peer->getRemoteAddress()->getIp(), 'hash' => $index->getHash()->getHex(), 'height' => $index->getHeight(), 'nTx' => $txcount, 'nSig' => $nSig]]);
        } catch (\Exception $e) {
            $header = $block->getHeader();
            $this->node->emit('event', ['error.onBlock', ['ip' => $peer->getRemoteAddress()->getIp(), 'hash' => $header->getHash()->getHex(), 'error' => $e->getMessage() . PHP_EOL . $e->getTraceAsString()]]);
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
     * @param Peer $peer
     */
    public function onPeerClose(Peer $peer)
    {
        $addr = $peer->getRemoteAddress();
        $this->container['debug']->log('p2p.disconnect', ['peer' => ['ip' => $addr->getIp(), 'port' => $addr->getPort()]]);
        $this->connectNextPeer();
    }

    /**
     * @param Peer $peer
     * @param Addr $addr
     */
    public function onAddr(Peer $peer, Addr $addr)
    {
        $list = [];
        foreach ($addr->getAddresses() as $ad) {
            $list[] = $ad->getIp();
        }

        $this->container['debug']->log('p2p.addr', ['ip' => $peer->getRemoteAddress()->getIp(), 'count' => count($addr), 'list' => $list]);
    }

    public function decodeServices($services)
    {
        $results = [];
        foreach ([
            'blockchain' => Services::NETWORK,
            'getutxo' => Services::GETUTXO,
            'bloom' => Services::BLOOM
                 ] as $str => $flag) {
            if (($services & $flag) == $flag) {
                $results[] = $str;
            }
        }

        return $results;
    }

    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $this->container = $container;

        // Setup listener if required
        if ($this->config->getItem('config', 'listen', '0')) {
            $listener = new Listener($this->params, $this->messages, new Server($this->loop), $this->loop);
            $this->manager->registerListener($listener);
        }

        $this->manager->on('outbound', function (Peer $peer) {
            $peer->on(Message::PING, array($this, 'onPing'));
            $peer->on(Message::BLOCK, [$this, 'onBlock']);
            $peer->on(Message::INV, [$this, 'onInv']);
            $peer->on(Message::HEADERS, [$this, 'onHeaders']);
            $peer->on(Message::ADDR, [$this, 'onAddr']);
            $peer->on(Message::GETHEADERS, [$this, 'onGetHeaders']);
            $peer->on('close', [$this, 'onPeerClose']);

            $addr = $peer->getRemoteAddress();
            $this->container['debug']->log('p2p.outbound', ['peer' => ['ip' => $addr->getIp(), 'port' => $addr->getPort(), 'services' => $this->decodeServices($addr->getServices())]]);

            $this->peersOutbound->add($peer);

            $chain = $this->node->chain();
            $height = $chain->getIndex()->getHeight();
            //$height = ($height != 0) ? $height - 1 : $height;

            $peer->getheaders($chain->getLocator($height));
        });

        $this->node->blocks()->on('block', function (ChainStateInterface $chainState, BlockInterface $block, BlockData $blockData) {
            if ($this->config->getItem('config', 'index_utxos', true)) {
                $utxos = $this->node->utxos();
                $utxos->update($chainState, $block, $blockData);
            }
        });

        $this->manager->on('inbound', function (Peer $peer) use ($container) {
            $peer->on(Message::PING, array($this, 'onPing'));

            $addr = $peer->getRemoteAddress();
            $container['debug']->log('p2p.inbound', ['peer' => ['ip' => $addr->getIp(), 'port' => $addr->getPort()]]);
            $this->peersInbound->add($peer);
        });

        $this
            ->locator
            ->queryDnsSeeds(1)
            ->then(function () {
                for ($i = 0; $i < 1; $i++) {
                    $this->connectNextPeer();
                }
            });
    }

    /**
     * @param Peer $peer
     * @return bool
     */
    public function checkAcceptablePeer(Peer $peer)
    {
        $remote = $peer->getRemoteVersion();
        if ($remote->getVersion() < Protocol::GETHEADERS) {
            return false;
        }

        if ($this->config->getItem('config', 'download_blocks', true) && $remote->getServices() & Services::NETWORK == 0) {
            return false;
        }

        return true;
    }

    /**
     * @return \React\Promise\PromiseInterface|static
     */
    public function connectNextPeer()
    {
        $addr = new Deferred();
        try {
            $addr->resolve($this->locator->popAddress());
        } catch (\Exception $e) {
            $this->locator->queryDnsSeeds(1)->then(function (Locator $locator) use ($addr) {
                $addr->resolve($locator->popAddress());
            });
        }

        return $addr
            ->promise()
            ->then(function (NetworkAddressInterface $host) {
                $goodPeer = new Deferred();

                $this
                    ->connector
                    ->connect($host)
                    ->then(function (Peer $peer) use ($goodPeer) {
                        $check = $this->checkAcceptablePeer($peer);

                        if (false === $check) {
                            $peer->close();
                            $goodPeer->reject();
                        } else {
                            $goodPeer->resolve($peer);
                        }

                    }, function ($e) use ($goodPeer) {
                        $goodPeer->reject();
                    });

                return $goodPeer->promise();
            })
            ->then(function (Peer $peer) {
                $this->manager->registerOutboundPeer($peer);
            }, function () {
                return $this->connectNextPeer();
            });

    }
}
