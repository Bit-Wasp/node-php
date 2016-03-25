<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Networking\Message;
use BitWasp\Bitcoin\Networking\Messages\Addr;
use BitWasp\Bitcoin\Networking\Messages\Block;
use BitWasp\Bitcoin\Networking\Messages\GetData;
use BitWasp\Bitcoin\Networking\Messages\GetHeaders;
use BitWasp\Bitcoin\Networking\Messages\Headers;
use BitWasp\Bitcoin\Networking\Messages\Inv;
use BitWasp\Bitcoin\Networking\Messages\Ping;
use BitWasp\Bitcoin\Networking\Messages\Pong;
use BitWasp\Bitcoin\Networking\Messages\Reject;
use BitWasp\Bitcoin\Networking\Peer\ConnectionParams;
use BitWasp\Bitcoin\Networking\Peer\Connector;
use BitWasp\Bitcoin\Networking\Peer\Listener;
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
use React\Socket\Server;

class MiniP2PService extends EventEmitter
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
     * @var DebugInterface
     */
    private $debug;
    
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
     * P2P constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->debug = $container['debug'];
        $this->loop = $container['loop'];
        $this->config = $container['config'];
        $this->peerStates = $container['p2p.states'];
        $this->peersInbound = $container['p2p.inbound'];
        $this->peersOutbound = $container['p2p.outbound'];

        $this->params = $container['p2p.params'];
        $this->params->requestTxRelay((bool)$this->config->getItem('config', 'tx_relay', false));

        $this->factory = new \BitWasp\Bitcoin\Networking\Factory($this->loop, Bitcoin::getNetwork());

        $dns = $this->factory->getDns();
        $this->messages = $this->factory->getMessages();

        if ((bool) $this->config->getItem('config', 'tor', true)) {
            $socks = new \Clue\React\Socks\Client('127.0.0.1:9050', $this->loop);
            $socks->setResolveLocal(false);

            $this->connector = new Connector($this->messages, $this->params, $this->loop, $dns, $socks->createConnector());
        } else {
            $this->connector = new Connector($this->messages, $this->params, $this->loop, $dns);
        }

        $this->manager = new Manager($this->connector);
        $this->locator = new Locator($dns);

        // Setup listener if required
        if ($this->config->getItem('config', 'listen', '0')) {
            $listener = new Listener($this->params, $this->messages, new Server($this->loop), $this->loop);
            $this->manager->registerListener($listener);
        }

        $this->manager->on('outbound', function (Peer $peer) {
            $this->setupPeer($peer);
            $peer->on('close', [$this, 'onPeerClose']);

            $addr = $peer->getRemoteAddress();
            $this->debug->log('p2p.outbound', ['peer' => ['ip' => $addr->getIp(), 'port' => $addr->getPort(), 'services' => $this->decodeServices($addr->getServices())]]);

            $state = $this->peerStates->fetch($peer);
            $this->peersOutbound->add($peer);
            $this->emit('outbound', [$state, $peer]);
        });

        $this->manager->on('inbound', function (Peer $peer) use ($container) {
            $this->setupPeer($peer);

            $addr = $peer->getRemoteAddress();
            $this->debug->log('p2p.inbound', ['peer' => ['ip' => $addr->getIp(), 'port' => $addr->getPort()]]);
            $this->peersInbound->add($peer);
            $state = $this->peerStates->fetch($peer);
            $this->emit('inbound', [$state, $peer]);

        });
    }

    /**
     * 
     */
    public function run()
    {

        $this
            ->locator
            ->queryDnsSeeds(1)
            ->then(function () {
                for ($i = 0; $i < 1; $i++) {
                    $this->connectNextPeer();
                }
            });
    }


    private function setupPeer(Peer $peer)
    {
        $peer->on(Message::PING, array($this, 'onPing'));
        $peer->on(Message::BLOCK, [$this, 'onBlock']);
        $peer->on(Message::INV, [$this, 'onInv']);
        $peer->on(Message::HEADERS, [$this, 'onHeaders']);
        $peer->on(Message::ADDR, [$this, 'onAddr']);
        $peer->on(Message::GETHEADERS, [$this, 'onGetHeaders']);
        $peer->on('close', [$this, 'onPeerClose']);
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
}
