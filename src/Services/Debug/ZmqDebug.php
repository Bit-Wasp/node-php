<?php

namespace BitWasp\Bitcoin\Node\Services\Debug;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainSegment;
use BitWasp\Bitcoin\Node\Index\Validation\BlockData;
use BitWasp\Bitcoin\Node\Index\Validation\HeadersBatch;
use BitWasp\Bitcoin\Node\NodeInterface;
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

        $node->headers()->on('tip', [$this, 'logTip']);
        $node->blocks()->on('block', [$this, 'logBlock']);
        $node->chains()->on('retarget', [$this, 'logRetarget']);

    }

    /**
     * @param ChainSegment $segment
     * @param int $prevBits
     * @param BlockIndexInterface $index
     */
    public function logRetarget(ChainSegment $segment, $prevBits, BlockIndexInterface $index)
    {
        $this->log('retarget', [
            'hash' => $index->getHash()->getHex(),
            'height' => $index->getHeight(),
            'prevBits' => $prevBits,
            'newBits' => $index->getHeader()->getBits(),
        ]);
    }

    /**
     * @param BlockIndexInterface $index
     * @param BlockInterface $block
     * @param BlockData $blockData
     */
    public function logBlock (BlockIndexInterface $index, BlockInterface $block, BlockData $blockData)
    {
        $this->log('block', [
            'hash' => $index->getHash()->getHex(),
            'height' => $index->getHeight(),
            'txs' => count($block->getTransactions()),
            'nFees' => gmp_strval($blockData->nFees, 10),
            'nSigOps' => $blockData->nSigOps,
            'utxos' => [
                'created' => count($blockData->remainingNew),
                'removed' => count($blockData->requiredOutpoints)
            ]
        ]);
    }

    /**
     * @param HeadersBatch $batch
     */
    public function logTip (HeadersBatch $batch)
    {
        $index = $batch->getTip()->getIndex();
        $this->log('tip', [
            'count' => count($batch->getIndices()),
            'tip' => [
                'hash' => $index->getHash()->getHex(),
                'height' => $index->getHeight(),
            ]
        ]);
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
