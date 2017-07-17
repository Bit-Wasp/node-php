<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Networking\Factory;
use BitWasp\Bitcoin\Networking\Ip\Ipv4;
use BitWasp\Bitcoin\Networking\Message;
use BitWasp\Bitcoin\Networking\Messages\Addr;
use BitWasp\Bitcoin\Networking\Messages\Block;
use BitWasp\Bitcoin\Networking\Messages\FeeFilter;
use BitWasp\Bitcoin\Networking\Messages\FilterAdd;
use BitWasp\Bitcoin\Networking\Messages\FilterClear;
use BitWasp\Bitcoin\Networking\Messages\FilterLoad;
use BitWasp\Bitcoin\Networking\Messages\GetAddr;
use BitWasp\Bitcoin\Networking\Messages\GetBlocks;
use BitWasp\Bitcoin\Networking\Messages\GetData;
use BitWasp\Bitcoin\Networking\Messages\GetHeaders;
use BitWasp\Bitcoin\Networking\Messages\Headers;
use BitWasp\Bitcoin\Networking\Messages\Inv;
use BitWasp\Bitcoin\Networking\Messages\MemPool;
use BitWasp\Bitcoin\Networking\Messages\MerkleBlock;
use BitWasp\Bitcoin\Networking\Messages\NotFound;
use BitWasp\Bitcoin\Networking\Messages\Ping;
use BitWasp\Bitcoin\Networking\Messages\Pong;
use BitWasp\Bitcoin\Networking\Messages\Reject;
use BitWasp\Bitcoin\Networking\Messages\SendHeaders;
use BitWasp\Bitcoin\Networking\Messages\Tx;
use BitWasp\Bitcoin\Networking\Peer\ConnectionParams;
use BitWasp\Bitcoin\Networking\Peer\Connector;
use BitWasp\Bitcoin\Networking\Peer\Locator;
use BitWasp\Bitcoin\Networking\Peer\Manager;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Networking\Protocol;
use BitWasp\Bitcoin\Networking\Services;
use BitWasp\Bitcoin\Networking\Structure\NetworkAddressInterface;
use BitWasp\Bitcoin\Node\Services\Debug\DebugInterface;
use BitWasp\Bitcoin\Node\Services\P2P\State\Peers;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerStateCollection;
use Evenement\EventEmitter;
use Packaged\Config\ConfigProviderInterface;
use Pimple\Container;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

class P2PService extends EventEmitter
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
     * @var PeerStateCollection
     */
    private $peerStates;

    /**
     * @var Connector
     */
    private $connector;

    /**
     * @var Manager
     */
    private $manager;

    /**
     * @var Locator
     */
    private $locator;

    /**
     * P2P constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->loop = $container['loop'];
        $this->config = $container['config'];
        $this->peerStates = $container['p2p.states'];

        /** @var ConnectionParams $params */
        $params = $container['p2p.params'];
        $factory = $container['p2p.factory'];

        if ((bool)$this->config->getItem('config', 'tor', false)) {
            //$socks = new Client('127.0.0.1:9050', $this->loop);
            //$socks->setResolveLocal(false);
            //$this->connector = new Connector($messages, $params, $this->loop, $dns, $socks->createConnector());
            $this->connector = $factory->getConnector($params);
        } else {
            $this->connector = $factory->getConnector($params);
        }

        $this->manager = $factory->getManager($this->connector);
        $this->locator = $factory->getLocator();

        // Setup listener if required
        if ($this->config->getItem('config', 'listen', '0')) {
            $listener = $factory->getListener($params, $factory->getAddress(new Ipv4('0.0.0.0', $factory->getSettings()->getDefaultP2PPort())));
            $this->manager->registerListener($listener);
        }

        /**
         * @var Peers $peersInbound
         * @var Peers $peersOutbound
         * @var DebugInterface $debug
         */
        $debug = $container['debug'];
        $peersInbound = $container['p2p.inbound'];
        $peersOutbound = $container['p2p.outbound'];

        $this->manager->on('outbound', function (Peer $peer) use ($peersOutbound, $debug) {
            $addr = $peer->getRemoteAddress();
            $debug->log('p2p.outbound', ['peer' => ['ip' => $addr->getIp(), 'port' => $addr->getPort(), 'services' => $this->decodeServices($addr->getServices())]]);
            $this->setupPeer($peer);

            $peersOutbound->add($peer);
            $peer->on('close', [$this, 'onPeerClose']);
        });

        $this->manager->on('inbound', function (Peer $peer) use ($peersInbound, $debug) {
            $addr = $peer->getRemoteAddress();
            $debug->log('p2p.inbound', ['peer' => ['ip' => $addr->getIp(), 'port' => $addr->getPort()]]);
            $this->setupPeer($peer);

            $peersInbound->add($peer);
        });

        $this->manager->on('outbound', [$this, 'onOutBoundPeer']);
        $this->manager->on('inbound', [$this, 'onInboundPeer']);

    }

    /**
     * @param Peer $peer
     */
    private function setupPeer(Peer $peer)
    {
        $peer->on(Message::ADDR, [$this, 'onAddr']);
        $peer->on(Message::BLOCK, [$this, 'onBlock']);
        $peer->on(Message::FEEFILTER, [$this, 'onFeeFilter']);
        $peer->on(Message::FILTERADD, [$this, 'onFilterAdd']);
        $peer->on(Message::FILTERCLEAR, [$this, 'onFilterClear']);
        $peer->on(Message::FILTERLOAD, [$this, 'onFilterLoad']);
        $peer->on(Message::GETADDR, [$this, 'onGetAddr']);
        $peer->on(Message::GETDATA, [$this, 'onGetData']);
        $peer->on(Message::GETBLOCKS, [$this, 'onGetBlocks']);
        $peer->on(Message::GETHEADERS, [$this, 'onGetHeaders']);
        $peer->on(Message::HEADERS, [$this, 'onHeaders']);
        $peer->on(Message::INV, [$this, 'onInv']);
        $peer->on(Message::MEMPOOL, [$this, 'onMemPool']);
        $peer->on(Message::MERKLEBLOCK, [$this, 'onMerkleBlock']);
        $peer->on(Message::NOTFOUND, [$this, 'onNotFound']);
        $peer->on(Message::PING, [$this, 'onPing']);
        $peer->on(Message::PONG, [$this, 'onPong']);
        $peer->on(Message::REJECT, [$this, 'onReject']);
        $peer->on(Message::SENDHEADERS, [$this, 'onSendHeaders']);
        $peer->on('close', [$this, 'onPeerClose']);
    }

    /**
     * @param int $services
     * @return array
     */
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
     *
     */
    public function run()
    {
        return $this
            ->locator
            ->queryDnsSeeds(1)
            ->then(function () {
                for ($i = 0; $i < 1; $i++) {
                    $this->connectNextPeer();
                }
            });
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

                    }, function () use ($goodPeer) {
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
     * @return Manager
     */
    public function manager()
    {
        return $this->manager;
    }

    /**
     * @return Locator
     */
    public function locator()
    {
        return $this->locator;
    }

    /**
     * @return Connector
     */
    public function connector()
    {
        return $this->connector;
    }

    /**
     * @param Peer $peer
     */
    public function onInboundPeer(Peer $peer)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit('inbound', [$state, $peer]);
    }

    /**
     * @param Peer $peer
     */
    public function onOutBoundPeer(Peer $peer)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit('outbound', [$state, $peer]);
    }

    /**
     * @param Peer $peer
     * @param Inv $inv
     */
    public function onInv(Peer $peer, Inv $inv)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::INV, [$state, $peer, $inv]);
    }

    /**
     * @param Peer $peer
     * @param GetHeaders $getHeaders
     */
    public function onGetHeaders(Peer $peer, GetHeaders $getHeaders)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::GETHEADERS, [$state, $peer, $getHeaders]);
    }

    /**
     * @param Peer $peer
     * @param Headers $headersMsg
     */
    public function onHeaders(Peer $peer, Headers $headersMsg)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::HEADERS, [$state, $peer, $headersMsg]);
    }

    /**
     * @param Peer $peer
     * @param Block $blockMsg
     */
    public function onBlock(Peer $peer, Block $blockMsg)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::BLOCK, [$state, $peer, $blockMsg]);
    }

    /**
     * @param Peer $peer
     * @param Ping $ping
     */
    public function onPing(Peer $peer, Ping $ping)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::PING, [$state, $peer, $ping]);
    }

    /**
     * @param Peer $peer
     * @param Pong $pong
     */
    public function onPong(Peer $peer, Pong $pong)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::PONG, [$state, $peer, $pong]);
    }

    /**
     * @param Peer $peer
     * @param GetData $getData
     */
    public function onGetData(Peer $peer, GetData $getData)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::GETDATA, [$state, $peer, $getData]);
    }

    /**
     * @param Peer $peer
     * @param FilterAdd $filterAdd
     */
    public function onFilterAdd(Peer $peer, FilterAdd $filterAdd)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::FILTERADD, [$state, $peer, $filterAdd]);
    }

    /**
     * @param Peer $peer
     * @param FilterClear $filterClear
     */
    public function onFilterClear(Peer $peer, FilterClear $filterClear)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::FILTERCLEAR, [$state, $peer, $filterClear]);
    }

    /**
     * @param Peer $peer
     * @param FilterLoad $filterLoad
     */
    public function onFilterLoad(Peer $peer, FilterLoad $filterLoad)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::FILTERLOAD, [$state, $peer, $filterLoad]);
    }

    /**
     * @param Peer $peer
     * @param Tx $tx
     */
    public function onTx(Peer $peer, Tx $tx)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::TX, [$state, $peer, $tx]);
    }

    /**
     * @param Peer $peer
     * @param MemPool $memPool
     */
    public function onMemPool(Peer $peer, MemPool $memPool)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::MEMPOOL, [$state, $peer, $memPool]);
    }

    /**
     * @param Peer $peer
     * @param NotFound $notFound
     */
    public function onNotFound(Peer $peer, NotFound $notFound)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::NOTFOUND, [$state, $peer, $notFound]);
    }

    /**
     * @param Peer $peer
     * @param GetAddr $getAddr
     */
    public function onGetAddr(Peer $peer, GetAddr $getAddr)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::GETADDR, [$state, $peer, $getAddr]);
    }

    /**
     * @param Peer $peer
     * @param GetBlocks $getBlocks
     */
    public function onGetBlocks(Peer $peer, GetBlocks $getBlocks)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::GETBLOCKS, [$state, $peer, $getBlocks]);
    }

    /**
     * @param Peer $peer
     * @param MerkleBlock $merkleBlock
     */
    public function onMerkleBlock(Peer $peer, MerkleBlock $merkleBlock)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::MERKLEBLOCK, [$state, $peer, $merkleBlock]);
    }

    /**
     * @param Peer $peer
     * @param FeeFilter $feeFilter
     */
    public function onFeeFilter(Peer $peer, FeeFilter $feeFilter)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::FEEFILTER, [$state, $peer, $feeFilter]);
    }

    /**
     * @param Peer $peer
     * @param SendHeaders $sendHeaders
     */
    public function onSendHeaders(Peer $peer, SendHeaders $sendHeaders)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::SENDHEADERS, [$state, $peer, $sendHeaders]);
    }

    /**
     * @param Peer $peer
     * @param Reject $reject
     */
    public function onReject(Peer $peer, Reject $reject)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::REJECT, [$state, $peer, $reject]);
    }

    /**
     * @param Peer $peer
     */
    public function onPeerClose(Peer $peer)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit('close', [$state, $peer]);
    }

    /**
     * @param Peer $peer
     * @param Addr $addr
     */
    public function onAddr(Peer $peer, Addr $addr)
    {
        $state = $this->peerStates->fetch($peer);
        $this->emit(Message::ADDR, [$state, $peer, $addr]);
    }
}
