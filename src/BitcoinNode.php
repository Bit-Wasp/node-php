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
use BitWasp\Bitcoin\Node\State\PeerState;
use BitWasp\Bitcoin\Node\State\PeerStateCollection;
use BitWasp\Buffertools\Buffer;
use Evenement\EventEmitter;
use Packaged\Config\Provider\Ini\IniConfigProvider;
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
     * @var ParamsInterface
     */
    public $params;

    /**
     * @param ParamsInterface $params
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
        $this->inventory = new KnownInventory();
        $this->db = new MySqlDb($this->config, false);
        $this->chains = new Chains($this->adapter);
        $this->pow = new ProofOfWork($this->adapter->getMath(), $params);
        echo "Headers \n";
        $this->headers = new Index\Headers($this->db, $this->adapter, $this->params, $this->pow, $this->chains);
        echo "Blocks  \n";
        $this->blocks = new Index\Blocks($this->db, $this->adapter, $this->params, $this->pow, $this->chains);
        $this->loadChainState();

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
        $this->db->stop();
        $this->loop->stop();
    }

    /**
     * @return \Packaged\Config\Provider\Ini\IniConfigProvider
     */
    private function loadConfig()
    {
        if (is_null($this->config)) {
            $file = getenv("HOME") . "/.bitcoinphp/bitcoin.ini";
            $this->config = new IniConfigProvider();
            $this->config->loadFile($file);
        }

        return $this->config;
    }

    private function loadChainState()
    {
        $states = $this->db->fetchChainState($this->headers);
        foreach ($states as $state) {
            $this->chains->trackChain($state);
        }
        $this->chains->checkTips();
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

            $peer->getheaders($this->chains->best()->getHeadersLocator());
        }
    }

    /**
     * @param ChainState $best
     * @param Peer $peer
     * @param PeerState $state
     */
    private function doDownloadBlocks(ChainState $best, Peer $peer, PeerState $state)
    {
        $headerHeight = $best->getChain()->getIndex()->getHeight();
        $blockHeight = $best->getLastBlock()->getHeight();

        $stopHeight = min($headerHeight, $blockHeight + 16);
        $hashStop = Buffer::hex($best->getChain()->getHashFromHeight($stopHeight), 32, $this->adapter->getMath());
        $locator = $best->getLocator($blockHeight, $hashStop);
        $peer->getblocks($locator);

        $state['hashstop'] = $hashStop;
        $state->useForBlockDownload(true);
        $state->addDownloadBlocks($stopHeight - $blockHeight - 1);
    }

    /**
     * @param Peer $peer
     */
    public function startBlockSync(Peer $peer)
    {
        $this->emit('blocks.syncing');

        $peerState = $this->peerState->fetch($peer);
        $this->doDownloadBlocks($this->chains->best(), $peer, $peerState);

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

            $peer->on('block', function (Peer $peer, Block $blockMsg) {
                $block = $blockMsg->getBlock();
                echo "Received block: " . $block->getHeader()->getBlockHash() . "\n";
                $best = $this->chains->best();
                $index = $this->blocks->accept($block, $this->headers);
                $best->updateLastBlock($index);
                $this->chains->checkTips();
                $peerState = $this->peerState->fetch($peer);
                if ($peerState->isBlockDownload()) {
                    $peerState->unsetDownloadBlock();
                    if (!$peerState->hasDownloadBlocks()) {
                        echo "do download blocks!\n";
                        $this->doDownloadBlocks($this->chains->best(), $peer, $peerState);
                    }
                }
            });

            $peer->on('inv', function (Peer $peer, Inv $inv) {
                echo "INV size: " . count($inv->getItems()) . "\n";
                $best = $this->chains->best();
                $bestHeaderIndex = $best->getChainIndex();
                $vFetch = [];
                $lastBlock = false;
                foreach ($inv->getItems() as $item) {
                    if ($item->isBlock() && !$this->inventory->check($item)) {
                        $this->inventory->save($item);
                        $vFetch[] = $item;
                        $lastBlock = $item->getHash();
                    }
                }

                if ($lastBlock) {
                    if (!$best->getChain()->containsHash($lastBlock->getHex())) {
                        echo "weird, we dont have this: " . $lastBlock->getHex() . "\n";
                        $peer->getheaders($best->getLocator($bestHeaderIndex->getHeight(), $lastBlock));
                    }
                }


                if (!empty($vFetch)) {
                    $peer->getdata($vFetch);
                }
            });

            $peer->on('getheaders', function (Peer $peer, GetHeaders $getHeaders ) {
                $state = $this->chains->best();
                $chain = $state->getChain();
                $locator = $getHeaders->getLocator();
                if (count($locator->getHashes()) == 0) {
                    $start = $locator->getHashStop()->getHex();
                } else {
                    $start = $this->db->findFork($chain, $locator);
                }

                $headers = $this->db->fetchNextHeaders($start);
                echo "Sending " . count($headers) . " headers \n";
                $peer->headers($headers);
            });

            // Process headers
            $peer->on('headers', function (Peer $peer, Headers $headers) {
                $state = $this->chains->best();
                $chain = $state->getChain();
                $startHeight = $chain->getIndex()->getHeight();

                $last = null;
                $vHeaders = $headers->getHeaders();
                $c = count($vHeaders);

                $tx = microtime(true);
                if ($c > 0) {
                    $this->headers->acceptBatch($vHeaders);
                }

                echo "tx took " . (microtime(true) - $tx) . "\n";
                $newHeight = $state->getChainIndex()->getHeight();
                echo "\nBest Headers ($c) went from $startHeight to " . $newHeight . "\n";

                if (count($vHeaders) == 2000) {
                    echo " ... continue syncing (current height = " . $newHeight . " ... \n";
                    $peer->getheaders($this->chains->best()->getHeadersLocator());
                } else {
                    $this->emit('headers.synced');
                }

                $this->startBlockSync($peer);
            });
        });

        $locator
            ->queryDnsSeeds(1)
            ->then(function (Locator $locator) use ($manager, $handler) {
                for($i = 0; $i < 2  ; $i++) {
                    $manager
                        ->connectNextPeer($locator)
                        ->then(function ($peer) {
                            $this->startHeaderSync($peer);
                        }, function () {
                            echo "connection wtf?\n";
                        });
                }
            }, function () {
                echo 'ERROR';
            });
    }

}