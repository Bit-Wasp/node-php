<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Networking\Factory as NetworkingFactory;
use BitWasp\Bitcoin\Networking\Messages\GetHeaders;
use BitWasp\Bitcoin\Networking\Messages\Headers;
use BitWasp\Bitcoin\Networking\Messages\Ping;
use BitWasp\Bitcoin\Networking\NetworkMessage;
use BitWasp\Bitcoin\Networking\Peer\Locator;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Networking\Structure\Inventory;
use BitWasp\Bitcoin\Node\State\PeerStateCollection;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

class BitcoinNode extends EventEmitter
{
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
     * @var bool
     */
    private $syncing = false;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var \Packaged\Config\ConfigProviderInterface
     */
    public $config;

    /**
     * @var MySqlDb
     */
    public $db;

    /**
     * @var Params
     */
    public $params;

    /**
     * @param Params $params
     * @param LoopInterface $loop
     */
    public function __construct(Params $params, LoopInterface $loop)
    {
        echo " [App] start \n";
        $start = microtime(true);
        $this->loadConfig();
        $this->loop = $loop;
        $this->params = $params;
        $this->adapter = Bitcoin::getEcAdapter();
        $this->network = Bitcoin::getNetwork();
        $this->netFactory = new NetworkingFactory($loop);
        $this->peerState = new PeerStateCollection();
        $this->consensus = new Consensus($this->adapter->getMath(), $this->params);

        $this->db = new MySqlDb($this->config, true);
        $this->headers = new Index\Headers($this->db, $this->adapter, $this->params);

        $this->on('headers.syncing', function () {
            echo " [App] ... HEADERS: syncing\n";
        });

        $this->on('headers.synced', function () {
            echo " [App] ... HEADERS: synced!\n";
        });

        echo " [App] Startup took: " . (microtime(true) - $start) . " seconds \n";
    }

    /**
     *
     */
    public function stop()
    {
        $this->loop->stop();
    }

    /**
     * @return \Packaged\Config\Provider\Ini\IniConfigProvider
     */
    public function loadConfig()
    {
        if (is_null($this->config)) {
            $file = getenv("HOME") . "/.bitcoinphp/bitcoin.ini";
            $this->config = new \Packaged\Config\Provider\Ini\IniConfigProvider();
            $this->config->loadFile($file);
        }

        return $this->config;
    }

    /**
     *
     */
    /*public function downloadBlocks()
    {
        $peerState = $this->peerState->storage();

        foreach ($peerState as $peer => $state) {
            if (count($state["downloadingBlocks"]) < 16) {

            }
        }
    }

    public function findFirstCommonBlock(BlockLocator $locator)
    {
        $hashes = $locator->getHashes();
        foreach ($hashes as $hash) {
            // todo: should really be Chain
            $find = $this->blocks->lookupByHash($hash->getHex());
            if (!is_null($find)) {
                return $find;
            }
        }

        return $this->blocks->genesis();
    }*/

    /**
     *
     */
    public function start()
    {
        echo "called start\n";
        $dns = $this->netFactory->getDns();
        $peerFactory = $this->netFactory->getPeerFactory($dns);
        $handler = $peerFactory->getPacketHandler();
        $locator = $peerFactory->getLocator();

        $txRelay = $this->config->getItem('config', 'tx_relay', false);
        $manager = $peerFactory->getManager(false);
        $manager->on('outbound', function (Peer $peer) {

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
                echo " [Peer] " . $peer->getRemoteAddr()->getIp() . " - " . $msg->getCommand(). "\n";
            });

            $peer->on('getheaders', function (Peer $peer, GetHeaders $getHeaders ) {
                if (!$this->isSyncing()) {
                    $locator = $getHeaders->getLocator();
                    if (count($locator->getHashes()) == 0) {
                        /*$find = $this->blocks->fetchByHash($locator->getHashStop()->getHex());
                        if ($find) {
                            $startAtIndex = $find;
                        }*/
                    } else {

                    }

                }
            });

            // Process headers
            $peer->on('headers', function (Peer $peer, Headers $headers) {
                $startHeight = $this->headers->getChainHeight();

                $last = null;
                $vHeaders = $headers->getHeaders();
                $c = count($vHeaders);

                try {
                    $tx = microtime(true);
                    $this->headers->acceptBatch($vHeaders);
                    echo "tx took " . (microtime(true) - $tx) . "\n";

                    $this->headers->checkActiveTip();
                    echo "\nHeaders ($c) went from $startHeight to " . $this->headers->getChainHeight() . "\n";

                    if (count($vHeaders) == 2000) {
                        echo " ... continue syncing (current height = " . $this->headers->getChainHeight() . " ... \n";
                        $peer->getheaders($this->headers->getLocatorCurrent());
                    } else {
                        $this->emit('headers.synced');
                    }
                } catch (\Exception $e) {
                    echo "ACCEPT EXCEPTION\n";
                    echo $e->getMessage() . "\n";
                    return;
                }
            });
        });

        $locator
            ->queryDnsSeeds(1)
            ->then(function (Locator $locator) use ($manager, $handler) {
                for($i = 0; $i < 2  ; $i++) {
                    $manager
                        ->connectNextPeer($locator)
                        ->then(function ($peer) {
                            echo "Start sync\n";
                            $this->startHeaderSync($peer);
                        }, function ($e) {
                            echo "connection wtf?\n";
                        });
                }
            }, function () {
                echo 'ERROR';
            });
    }

    /**
     * @param Peer $peer
     */
    public function startHeaderSync(Peer $peer)
    {
        if (!$this->isSyncing()) {
            $this->syncing = true;
            $state = $this->peerState->fetch($peer);
            $state->useForDownload();
            $this->emit('headers.syncing');
            $locator = $this->headers->getLocatorCurrent();
            $peer->getheaders($locator);
        }
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
     * @return bool
     */
    public function isSyncing()
    {
        return $this->syncing;
    }
}