<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;

use BitWasp\Bitcoin\Networking\Message;
use BitWasp\Bitcoin\Networking\Messages\Block;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Networking\Structure\Inventory;
use BitWasp\Bitcoin\Node\Chain\HeadersBatch;
use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\P2P\Request\BlockDownloader;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerState;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use Evenement\EventEmitter;
use Packaged\Config\ConfigProviderInterface;
use Pimple\Container;

class P2PBlocksService extends EventEmitter
{
    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * @var ConfigProviderInterface
     */
    private $config;

    /**
     * @var BlockDownloader
     */
    private $blockDownload;

    /**
     * P2PBlocksService constructor.
     * @param NodeInterface $node
     * @param Container $container
     */
    public function __construct(NodeInterface $node, Container $container)
    {
        $this->node = $node;
        $this->config = $container['config'];
        $this->blockDownload = new BlockDownloader($this->node->chains(), $container['p2p.states'], $container['p2p.outbound']);

        /** @var P2PService $p2p */
        $p2p = $container['p2p'];
        $p2p->on(Message::BLOCK, [$this, 'onBlock']);

        /** @var P2PHeadersService $headers */
        $headers = $container['p2p.headers'];
        $headers->on('headers', [$this, 'onHeaders']);
        
        /** @var P2PInvService $inv */
        $inv = $container['p2p.inv'];
        $inv->on('blocks', [$this, 'onInvBlocks']);
    }

    /**
     * @param PeerState $state
     * @param Peer $peer
     * @param HeadersBatch $batch
     */
    public function onHeaders(PeerState $state, Peer $peer, HeadersBatch $batch)
    {
        if (count($batch->getIndices()) < 2000) {
            $this->blockDownload->start($batch->getTip(), $peer);
        }
    }

    /**
     * @param PeerState $state
     * @param Peer $peer
     * @param Inventory[] $vInv
     */
    public function onInvBlocks(PeerState $state, Peer $peer, array $vInv)
    {
        $best = $this->node->chain();
        $blockView = $best->bestBlocksCache();
        $this->blockDownload->advertised($best, $blockView, $peer, $vInv);
    }

    /**
     * @param PeerState $state
     * @param Peer $peer
     * @param Block $blockMsg
     */
    public function onBlock(PeerState $state, Peer $peer, Block $blockMsg)
    {
        $node = $this->node;
        $best = $node->chain();
        $block = $blockMsg->getBlock();

        $chainsIdx = $node->chains();
        $headerIdx = $node->headers();
        $blockIndex = $node->blocks();

        $checkSignatures = (bool)$this->config->getItem('config', 'check_signatures', true);
        $checkSize = (bool)$this->config->getItem('config', 'check_block_size', true);
        $checkMerkleRoot = (bool)$this->config->getItem('config', 'check_merkle_root', true);

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
}
