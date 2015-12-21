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
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Config\ConfigLoader;
use BitWasp\Bitcoin\Node\Request\BlockDownloader;
use BitWasp\Bitcoin\Node\Validation\BlockCheck;
use BitWasp\Bitcoin\Node\Validation\HeaderCheck;
use BitWasp\Bitcoin\Node\Validation\ScriptCheck;
use BitWasp\Bitcoin\Node\State\Peers;
use BitWasp\Bitcoin\Node\State\PeerStateCollection;
use BitWasp\Bitcoin\Node\Validation\ScriptValidationState;
use BitWasp\Bitcoin\Node\Zmq\Notifier;
use BitWasp\Bitcoin\Node\Zmq\ScriptThreadControl;
use BitWasp\Bitcoin\Node\Zmq\UserControl;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\ZMQ\Context as ZMQContext;

class BitcoinNode extends EventEmitter implements NodeInterface
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
        $math = Bitcoin::getMath();
        $adapter = Bitcoin::getEcAdapter($math);

        $zmq = new ZMQContext($loop);
        $this
            ->initControl($zmq)
            ->initConfig();

        $this->notifier = new Notifier($zmq, $this);
        $this->chains = new Chains($adapter, $params);
        $this->chains->on('newtip', function (ChainStateInterface $state) {
            $index = $state->getChainIndex();
            $this->notifier->send('chain.newtip', ['hash' => $index->getHash()->getHex(), 'height' => $index->getHeight(), 'work' => $index->getWork()]);
        });

        $this->peerState = new PeerStateCollection();
        $this->peersInbound = new Peers();
        $this->peersOutbound = new Peers();

        $db = new Db($this->config, false);
        $consensus = new Consensus($math, $params);

        $zmqScript = new ScriptCheck($adapter);
        $this->pow = new ProofOfWork($math, $params);
        $this->headers = new Index\Headers($db, $consensus, $math, $this->chains, new HeaderCheck($consensus, $adapter, $this->pow));
        $this->blocks = new Index\Blocks($db, $adapter, $this->chains, $consensus, new BlockCheck($consensus, $adapter, $zmqScript));

        $genesis = $params->getGenesisBlock();
        $this->headers->init($genesis->getHeader());
        $this->blocks->init($genesis);

        $this->utxo = new Index\UtxoIdx($this->chains, $db);
        $this->blockDownload = new BlockDownloader($this->chains, $this->peerState, $this->peersOutbound);

        $this->db = $db;
        $this->loop = $loop;
        $this->params = $params;
        $this->ecAdapter = $adapter;
        $this->initChainState();

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

            $chainState = null;
            $indexLast = null;

            $this->headers->acceptBatch($vHeaders, $chainState, $indexLast);

            /**
             * @var ChainStateInterface $chainState
             * @var BlockIndexInterface $indexLast
             */

            $this->chains->checkTips();
            $this->peerState->fetch($peer)->updateBlockAvailability($chainState, $indexLast->getHash());

            if ($count === 2000) {
                $peer->getheaders($chainState->getHeadersLocator());
            }

            if ($count < 2000) {
                $this->blockDownload->start($chainState, $peer);
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
        $best = $this->chain();

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
        $this->notifier->send('p2p.inv', ['blocks' => count($blocks), 'txs' => count($txs)]);

        if (count($blocks) !== 0) {
            $blockView = $best->bestBlocksCache();
            $this->blockDownload->advertised($best, $blockView, $peer, $blocks);
        }

        if (count($vFetch) !== 0) {
            $peer->getdata($vFetch);
        }
    }

    /**
     * @param Peer $peer
     * @param Block $blockMsg
     */
    public function onBlock(Peer $peer, Block $blockMsg)
    {
        $best = $this->chain();
        $block = $blockMsg->getBlock();

        try {
            $state = new ScriptValidationState($this->loop, true);
            $index = $this->blocks->accept($block, $this->headers, $state);
            $this->notifier->send('p2p.block', ['hash' => $index->getHash()->getHex(), 'height' => $index->getHeight()]);

            $this->chains->checkTips();
            $this->blockDownload->received($best, $peer, $index->getHash());

        } catch (\Exception $e) {
            $header = $block->getHeader();
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
                for ($i = 0; $i < 1; $i++) {
                    $manager->connectNextPeer($locator);
                }
            });
    }
}
