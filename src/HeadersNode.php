<?php

namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Networking\Factory as NetworkingFactory;
use BitWasp\Bitcoin\Networking\Messages\Block;
use BitWasp\Bitcoin\Networking\Messages\GetHeaders;
use BitWasp\Bitcoin\Networking\Messages\Headers;
use BitWasp\Bitcoin\Networking\Messages\Inv;
use BitWasp\Bitcoin\Networking\Messages\Ping;
use BitWasp\Bitcoin\Networking\Peer\Locator;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\Chains;
use BitWasp\Bitcoin\Node\Chain\ChainsInterface;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Config\ConfigLoader;
use BitWasp\Bitcoin\Node\Request\BlockDownloader;
use BitWasp\Bitcoin\Node\Validation\BlockCheck;
use BitWasp\Bitcoin\Node\Validation\HeaderCheck;
use BitWasp\Bitcoin\Node\Validation\ScriptCheck;
use BitWasp\Bitcoin\Node\State\Peers;
use BitWasp\Bitcoin\Node\State\PeerStateCollection;
use BitWasp\Bitcoin\Node\Zmq\Notifier;
use BitWasp\Bitcoin\Node\Zmq\ScriptThreadControl;
use BitWasp\Bitcoin\Node\Zmq\UserControl;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\ZMQ\Context as ZMQContext;

class HeadersNode extends EventEmitter implements NodeInterface
{

    /**
     * @var \BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface
     */
    private $ecAdapter;

    /**
     * @var Db
     */
    private $db;

    /**
     * @var Notifier
     */
    private $notifier;

    /**
     * @var PeerStateCollection
     */
    private $peerState;

    /**
     * @var \Packaged\Config\ConfigProviderInterface
     */
    protected $config;

    /**
     * @var Index\Blocks
     */
    protected $blocks;

    /**
     * @var Index\Headers
     */
    protected $headers;

    /**
     * @var ChainsInterface
     */
    protected $chains;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var ParamsInterface
     */
    private $params;

    /**
     * @var Index\UtxoIdx
     */
    protected $utxo;

    /**
     * @var BlockDownloader
     */
    private $blockDownload;

    /**
     * @var Peers
     */
    private $peersInbound;

    /**
     * @var Peers
     */
    private $peersOutbound;

    /**
     * @var ProofOfWork
     */
    protected $pow;

    /**
     * @param ParamsInterface $params
     * @param LoopInterface $loop
     */
    public function __construct(ParamsInterface $params, LoopInterface $loop)
    {
        $start = microtime(true);

        $math = Bitcoin::getMath();
        $adapter = Bitcoin::getEcAdapter($math);

        $zmq = new ZMQContext($loop);
        $this
            ->initControl($zmq)
            ->initConfig();

        $this->loop = $loop;
        $this->params = $params;
        $this->ecAdapter = $adapter;
        $this->notifier = new Notifier($zmq, $this);
        $this->chains = new Chains($adapter, $this->params);
        $this->chains->on('retarget', function (ChainState $state, BlockIndexInterface $index) {
            $this->notifier->send('chain.retarget', [
                'tip' => $index->getHash()->getHex(),
                'height' => $index->getHeight(),
                'nBits' => $index->getHeader()->getBits()->getHex()
            ]);
        });

        $this->chains->on('newtip', function (ChainState $state) use ($math) {
            $index = $state->getChainIndex();
            $hash = $index->getHash()->getHex();
            $height = $index->getHeight();
            $this->notifier->send('chain.newtip', [
                'hash' => $hash,
                'height' => $height,
                'work' => $index->getWork()
            ]);
        });

        $this->inventory = new KnownInventory();
        $this->peerState = new PeerStateCollection();
        $this->peersInbound = new Peers();
        $this->peersOutbound = new Peers();

        $this->db = new Db($this->config, false);
        $consensus = new Consensus($math, $params);

        $zmqScript = new ScriptCheck($adapter);
        $this->pow = new ProofOfWork($math, $params);
        $this->headers = new Index\Headers($this->db, $consensus, $math, $this->chains, new HeaderCheck($consensus, $adapter, $this->pow));
        $this->blocks = new Index\Blocks($this->db, $adapter, $this->chains, $consensus, new BlockCheck($consensus, $adapter, $zmqScript));

        $genesis = $params->getGenesisBlock();
        $this->headers->init($genesis->getHeader());
        $this->blocks->init($genesis);
        $this->initChainState();

        $this->utxo = new Index\UtxoIdx($this->chains, $this->db);
        $this->blockDownload = new BlockDownloader($this->chains, $this->peerState, $this->peersOutbound);

        echo ' [App] Startup took: ' . (microtime(true) - $start) . ' seconds ' . PHP_EOL;
    }

    /**
     * @return void
     */
    public function stop()
    {
        $this->peersInbound->close();
        $this->peersOutbound->close();
        $this->loop->stop();
        $this->db->stop();
    }

    /**
     * @return $this
     */
    private function initControl(ZMQContext $context)
    {
        $this->control = new UserControl($context, $this, new ScriptThreadControl($context));
        return $this;
    }

    /**
     * @return $this
     */
    private function initConfig()
    {
        $this->config = (new ConfigLoader())->load();

        return $this;
    }

    /**
     * @return $this
     */
    private function initChainState()
    {
        $states = $this->db->fetchChainState($this->headers);
        foreach ($states as $state) {
            $this->chains->trackState($state);
        }
        $this->chains->checkTips();
        return $this;
    }

    /**
     * @return ChainStateInterface
     */
    public function chain()
    {
        return $this->chains->best();
    }

    /**
     * @return ChainsInterface
     */
    public function chains()
    {
        return $this->chains;
    }

    /**
     * @param Peer $peer
     * @param GetHeaders $getHeaders
     */
    public function onGetHeaders(Peer $peer, GetHeaders $getHeaders)
    {
        return;
        $chain = $this->chain()->getChain();
        $locator = $getHeaders->getLocator();
        if (count($locator->getHashes()) === 0) {
            $start = $locator->getHashStop()->getHex();
        } else {
            $start = $this->db->findFork($chain, $locator);
        }

        $headers = $this->db->fetchNextHeaders($start);
        $peer->headers($headers);
        echo 'Sending from ' . $start . ' + ' . count($headers) . ' headers ' . PHP_EOL;
    }

    /**
     * @param Peer $peer
     * @param Headers $headers
     */
    public function onHeaders(Peer $peer, Headers $headers)
    {
        $vHeaders = $headers->getHeaders();
        $count = count($vHeaders);

        if ($count > 0) {

            $state = null;
            $indexLast = null;
            $this->headers->acceptBatch($vHeaders, $state, $indexLast);
            /**
             * @var ChainState $state
             * @var BlockIndexInterface $indexLast
             */

            $this->chains->checkTips();

            $this->peerState->fetch($peer)->updateBlockAvailability($state, $indexLast->getHash());

            if (2000 === $count) {
                $peer->getheaders($state->getHeadersLocator());
            }

            if ($count < 2000) {
                $this->blockDownload->start($state, $peer);
            }
        }

        $this->notifier->send('p2p.headers', ['count' => $count]);

    }

    /**
     * @param Peer $peer
     * @param Inv $inv
     */
    public function onInv(Peer $peer, Inv $inv)
    {
        $state = $this->chain();
        $best = $state->getChain();

        $lastUnknown = null;
        foreach ($inv->getItems() as $item) {
            if ($item->isBlock() && $lastUnknown == null && !$best->containsHash($item->getHash())) {
                $lastUnknown = $item->getHash();
            }
        }

        if (null !== $lastUnknown) {
            $peer->getheaders($state->getHeadersLocator($lastUnknown));
            $this->peerState->fetch($peer)->updateBlockAvailability($state, $lastUnknown);
        }
    }

    /**
     * @param Peer $peer
     * @param Block $blockMsg
     */
    public function onBlock(Peer $peer, Block $blockMsg)
    {

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
     *
     */
    public function start()
    {
        $txRelay = $this->config->getItem('config', 'tx_relay', false);
        $netFactory = new NetworkingFactory($this->loop);

        $dns = $netFactory->getDns();
        $peerFactory = $netFactory->getPeerFactory($dns);
        $handler = $peerFactory->getPacketHandler();
        $handler->on('ping', array($this, 'onPing'));

        $locator = $peerFactory->getLocator();
        $manager = $peerFactory->getManager($txRelay);
        $manager->registerHandler($handler);

        // Setup listener if required
        if ($this->config->getItem('config', 'listen', '0')) {
            $server = new \React\Socket\Server($this->loop);
            $listener = $peerFactory->getListener($server);
            $manager->registerListener($listener);
        }

        $manager->on('outbound', function (Peer $peer) {
            $peer->on('block', array ($this, 'onBlock'));
            $peer->on('inv', array ($this, 'onInv'));
            $peer->on('getheaders', array ($this, 'onGetHeaders'));
            $peer->on('headers', array ($this, 'onHeaders'));

            $addr = $peer->getRemoteAddr();
            $this->notifier->send('peer.outbound.new', ['peer' =>['ip'=>$addr->getIp(), 'port' => $addr->getPort()]]);

            $this->peersOutbound->add($peer);

            $chain = $this->chain();
            $height = $chain->getChain()->getIndex()->getHeight();
            $height = ($height != 0) ? $height - 1 : $height;

            $peer->getheaders($chain->getLocator($height));
        });

        $manager->on('inbound', function (Peer $peer) {
            $this->peersInbound->add($peer);
        });

        $locator
            ->queryDnsSeeds(1)
            ->then(function (Locator $locator) use ($manager, $handler) {
                for ($i = 0; $i < 8; $i++) {
                    $manager->connectNextPeer($locator);
                }
            });
    }
}
