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
use BitWasp\Bitcoin\Networking\NetworkMessage;
use BitWasp\Bitcoin\Networking\Peer\Locator;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Networking\Structure\Inventory;
use BitWasp\Bitcoin\Node\Chain\Chains;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\Routine\BlockCheck;
use BitWasp\Bitcoin\Node\Routine\HeaderCheck;
use BitWasp\Bitcoin\Node\Routine\ZmqScriptCheck;
use BitWasp\Bitcoin\Node\State\Peers;
use BitWasp\Bitcoin\Node\State\PeerStateCollection;
use Evenement\EventEmitter;
use Packaged\Config\Provider\Ini\IniConfigProvider;
use React\EventLoop\LoopInterface;
use React\ZMQ\Context as ZMQContext;

class BitcoinNode extends EventEmitter
{

    /**
     * @var \Packaged\Config\ConfigProviderInterface
     */
    public $config;

    /**
     * @var Db
     */
    private $db;

    /**
     * @var Index\Blocks
     */
    private $blocks;

    /**
     * @var Index\Headers
     */
    private $headers;

    /**
     * @var \BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface
     */
    private $adapter;

    /**
     * @var NetworkingFactory
     */
    private $netFactory;

    /**
     * @var PeerStateCollection
     */
    private $peerState;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var ParamsInterface
     */
    private $params;

    /**
     * @var ZMQContext
     */
    private $zmq;

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
     * @param ParamsInterface $params
     * @param LoopInterface $loop
     */
    public function __construct(ParamsInterface $params, LoopInterface $loop)
    {
        echo " [App] start \n";
        $start = microtime(true);

        $math = Bitcoin::getMath();
        $adapter = Bitcoin::getEcAdapter($math);

        $this->zmq = new ZMQContext($loop);
        $this
            ->initControl()
            ->initConfig();

        $this->loop = $loop;
        $this->params = $params;
        $this->adapter = $adapter;
        $this->chains = new Chains($adapter);
        $this->inventory = new KnownInventory();
        $this->peerState = new PeerStateCollection();
        $this->peersInbound = new Peers();
        $this->peersOutbound = new Peers();
        $this->netFactory = new NetworkingFactory($loop);

        $this->db = new Db($this->config, false);
        $consensus = new Consensus($math, $params);

        $zmqScript = new ZmqScriptCheck(new \ZMQContext());
        $this->headers = new Index\Headers($this->db, $consensus, $math, new HeaderCheck($consensus, $adapter, new ProofOfWork($math, $params)));
        $this->blocks = new Index\Blocks($this->db, $adapter, $consensus, new BlockCheck($consensus, $adapter, $zmqScript));

        $genesis = $params->getGenesisBlock();
        $this->headers->init($genesis->getHeader());
        $this->blocks->init($genesis);
        $this->initChainState();

        $this->blockDownload = new BlockDownloader($this->chains, $this->peerState, $this->peersOutbound);

        $this->on('blocks.syncing', function () {
            echo " [App] ... BLOCKS: syncing\n";
        });

        $this->on('headers.syncing', function () {
            echo " [App] ... HEADERS: syncing\n";
        });

        $this->on('headers.synced', function () {
            echo " [App] ... HEADERS: synced!\n";
        });

        echo " [App] Startup took: " . (microtime(true) - $start) . " seconds \n";
    }

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
    private function initControl()
    {
        $subControl = $this->zmq->getSocket(\ZMQ::SOCKET_PUB);
        $subControl->bind("tcp://127.0.0.1:5594");

        $control = $this->zmq->getSocket(\ZMQ::SOCKET_PULL);
        $control->bind('tcp://127.0.0.1:5560');
        $control->on('message', function ($e) use ($subControl) {
            if ($e == 'shutdown') {
                echo "Shutdown\n";
                $subControl->send('shutdown');
                $this->stop();
            }
        });

        return $this;
    }

    /**
     * @return $this
     */
    private function initConfig()
    {
        if (is_null($this->config)) {
            $file = getenv("HOME") . "/.bitcoinphp/bitcoin.ini";
            $this->config = new IniConfigProvider();
            $this->config->loadFile($file);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function initChainState()
    {
        $states = $this->db->fetchChainState($this->headers);
        foreach ($states as $state) {
            $this->chains->trackChain($state);
        }
        $this->chains->checkTips();
        return $this;
    }

    /**
     * @param Inventory $inventory
     * @return bool
     */
    public function checkInventory(Inventory $inventory)
    {
        if ($inventory->isBlock()) {
            //return isset($this->blocks->hashIndex[$inventory->getHash()->getHex()]);
        }

        if ($inventory->isTx()) {
            return $this->db->transactions->fetch($inventory->getHash()->getHex()) !== null;
        }

        return true;
    }

    /**
     * @return ChainState
     */
    public function chain()
    {
        return $this->chains->best();
    }

    /**
     * @param Peer $peer
     * @param GetHeaders $getHeaders
     */
    public function onGetHeaders(Peer $peer, GetHeaders $getHeaders )
    {
        return;
        $chain = $this->chain()->getChain();
        $locator = $getHeaders->getLocator();
        if (count($locator->getHashes()) == 0) {
            $start = $locator->getHashStop()->getHex();
        } else {
            $start = $this->db->findFork($chain, $locator);
        }

        $headers = $this->db->fetchNextHeaders($start);
        $peer->headers($headers);
        echo "Sending from " . $start . " + " . count($headers) . " headers \n";
    }

    /**
     * @param Peer $peer
     * @param Headers $headers
     */
    public function onHeaders(Peer $peer, Headers $headers)
    {
        $state = $this->chain();
        $vHeaders = $headers->getHeaders();
        $count = count($vHeaders);
        if ($count > 0) {

            try {
                $this->headers->acceptBatch($state, $vHeaders);
                $this->chains->checkTips();
                if (2000 == $count) {
                    $peer->getheaders($state->getHeadersLocator());
                }

                $last = end($vHeaders);
                $this->peerState->fetch($peer)->updateBlockAvailability($state, $last->getHash()->getHex());
                $this->blockDownload->start($state, $peer);

            } catch (\Exception $e) {
                echo $e->getMessage() . "\n";
            }
        }
    }

    /**
     * @param Peer $peer
     * @param Inv $inv
     */
    public function onInv(Peer $peer, Inv $inv)
    {
        echo "INV size: " . count($inv->getItems()) . "\n";
        $best = $this->chain();

        $vFetch = [];
        $blocks = [];
        foreach ($inv->getItems() as $item) {
            if ($item->isBlock()) {
                $blocks[] = $item;
            } else {
                $vFetch[] = $item;
            }
        }

        if (!empty($blocks)) {
            $blockView = $best->bestBlocksCache();
            $this->blockDownload->advertised($best, $blockView, $peer, $blocks);
        }

        if (!empty($vFetch)) {
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
            $this->blocks->accept($best, $block, $this->headers);
            $this->chains->checkTips();
            $this->blockDownload->received($best, $peer, $block->getHeader()->getHash()->getHex());

        } catch (\Exception $e) {
            $header = $block->getHeader();
            echo "Failed to accept block\n";
            if ($best->getChain()->containsHash($block->getHeader()->getPrevBlock())) {
                if ($header->getPrevBlock() == $best->getLastBlock()->getHash()) {
                    echo $block->getHeader()->getHash()->getHex() . "\n";
                    echo $block->getHex() . "\n";
                    echo 'We have prevblockIndex, so this is weird.';
                    echo $e->getTraceAsString() . PHP_EOL;
                    echo $e->getMessage() . PHP_EOL;
                } else {
                    echo "Didn't elongate the chain, probably from the future..\n";
                }
            }
        }
    }

    /**
     *
     */
    public function start()
    {
        $dns = $this->netFactory->getDns();
        $peerFactory = $this->netFactory->getPeerFactory($dns);
        $handler = $peerFactory->getPacketHandler();
        $locator = $peerFactory->getLocator();

        $txRelay = $this->config->getItem('config', 'tx_relay', false);
        $manager = $peerFactory->getManager($txRelay);
        $manager->on('outbound', function (Peer $peer) {
            $this->peersOutbound->add($peer);
        });
        $manager->on('inbound', function (Peer $peer) {
            $this->peersInbound->add($peer);
        });
        $manager->registerHandler($handler);

        // Setup listener if required
        if ($this->config->getItem('config', 'listen', '0')) {
            echo ' [App - networking] enable listener';
            $server = new \React\Socket\Server($this->loop);
            $listener = $peerFactory->getListener($server);
            $manager->registerListener($listener);
        }

        $handler->on('ping', function (Peer $peer, Ping $ping) {
            $peer->pong($ping);
        });

        // Only for outbound peers
        $handler->on('outbound', function (Peer $peer) {
            $peer->on('msg', function (Peer $peer, NetworkMessage $msg) {
                $payload = $msg->getPayload();

                if ($msg->getCommand() == 'block') {
                    /** @var Block $payload */
                    echo " [Peer] " . $peer->getRemoteAddr()->getIp() . " - block - " . $payload->getBlock()->getHeader()->getHash()->getHex() . "\n";
                } else {
                    echo " [Peer] " . $peer->getRemoteAddr()->getIp() . " - " . $msg->getCommand(). "\n";
                }
            });

            $peer->on('block', array ($this, 'onBlock'));
            $peer->on('inv', array ($this, 'onInv'));
            $peer->on('getheaders', array ($this, 'onGetHeaders'));
            $peer->on('headers', array ($this, 'onHeaders'));
        });

        $locator
            ->queryDnsSeeds(1)
            ->then(function (Locator $locator) use ($manager, $handler) {
                for ($i = 0; $i < 2 ; $i++) {
                    $manager
                        ->connectNextPeer($locator)
                        ->then(function (Peer $peer) {
                            $chain = $this->chain();
                            $peer->getheaders($chain->getHeadersLocator());
                            //$this->blockDownload->start($chain, $peer);

                            echo "init\n";
                        });
                }
            }, function () {
                echo 'ERROR';
            });
    }

}