<?php

namespace BitWasp\Bitcoin\Node\Services\Debug;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainSegment;
use BitWasp\Bitcoin\Node\Index\Validation\BlockData;
use BitWasp\Bitcoin\Node\Index\Validation\HeadersBatch;
use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Buffertools\BufferInterface;
use React\ZMQ\Context;
use React\ZMQ\SocketWrapper;

class ZmqDebug implements DebugInterface
{
    /**
     * @var SocketWrapper
     */
    private $socket;

    /**
     * ZmqDebug constructor.
     * @param NodeInterface $node
     * @param Context $context
     */
    public function __construct(NodeInterface $node, Context $context)
    {
        $this->socket = $context->getSocket(\ZMQ::SOCKET_PUB);
        $this->socket->bind('tcp://127.0.0.1:5566');
        $node->on('event', function ($event, array $params) {
            $this->log($event, $params);
        });

        $node->headers()->on('tip', function (HeadersBatch $batch) {
            $index = $batch->getTip()->getIndex();
            $this->log('newtip', ['count' => count($batch->getIndices()), 'tip' => [
                'hash' => $index->getHash()->getHex(),
                'height' => $index->getHeight(),
            ]]);
        });
        
        $node->blocks()->on('block', function (BlockIndexInterface $index, BlockInterface $block, BlockData $blockData) {
            $this->log('block', [
                'hash' => $index->getHash()->getHex(),
                'height' => $index->getHeight(),
                'txs' => count($block->getTransactions()),
                'nFees' => $blockData->nFees,
                'nSigOps' => $blockData->nSigOps,
                'utxos' => [
                    'created' => count($blockData->remainingNew),
                    'removed' => count($blockData->requiredOutpoints)
                ]
            ]);
        });

        $node->chains()->on('retarget', function (ChainSegment $segment, BufferInterface $bits, BlockIndexInterface $index) {
            $this->log('retarget', [
                'hash' => $index->getHash()->getHex(),
                'height' => $index->getHeight(),
                'prevBits' => $bits->getHex(),
                'newBits' => $index->getHeader()->getBits()->getHex(),
            ]);
        });
    }

    /**
     * @param string $subject
     * @param array $params
     */
    public function log($subject, array $params = [])
    {
        $this->socket->send(json_encode(['event' => $subject, 'params' => $params]));
    }
}
