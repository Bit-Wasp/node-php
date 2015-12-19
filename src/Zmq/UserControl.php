<?php

namespace BitWasp\Bitcoin\Node\Zmq;

use BitWasp\Bitcoin\Node\NodeInterface;
use \React\ZMQ\Context;

class UserControl
{
    /**
     * @var \React\ZMQ\SocketWrapper
     */
    private $socket;

    /**
     * @param Context $context
     * @param NodeInterface $node
     * @param ScriptThreadControl $threadControl
     */
    public function __construct(Context $context, NodeInterface $node, ScriptThreadControl $threadControl)
    {
        $cmdControl = $context->getSocket(\ZMQ::SOCKET_REP);
        $cmdControl->bind('tcp://127.0.0.1:5560');
        $cmdControl->on('message', function ($e) use ($threadControl, $node) {
            $result = ['error' => 'Unrecognized command'];
            if ($e === 'shutdown') {
                $threadControl->shutdown();
                $result = $this->onShutdown($threadControl);
            } elseif ($e === 'info') {
                $result = $this->onInfo($node);
            } elseif ($e === 'chains') {
                $result = $this->onChains($node);
            }

            $this->socket->send(json_encode($result, JSON_PRETTY_PRINT));

            if ($e === 'shutdown') {
                $node->stop();
            }
        });

        $this->socket = $cmdControl;
    }

    /**
     * @param ScriptThreadControl $threadControl
     * @return array
     */
    public function onShutdown(ScriptThreadControl $threadControl)
    {
        $threadControl->shutdown();

        return [
            'message' => 'Shutting down'
        ];
    }

    /**
     * @param NodeInterface $node
     * @return array
     */
    public function onInfo(NodeInterface $node)
    {
        $chain = $node->chain();
        $bestHeaderIdx = $chain->getChain()->getIndex();
        $bestHeader = $bestHeaderIdx->getHeader();

        $bestBlockIdx = $chain->getLastBlock();
        $bestBlockHeader = $bestBlockIdx->getHeader();

        $nChain = count($node->chains()->getChains());

        $info = [
            'height' => $bestHeaderIdx->getHeight(),
            'work' => $bestHeaderIdx->getWork(),
            'best_header' => [
                'height' => $bestHeaderIdx->getHeight(),
                'hash' => $bestHeaderIdx->getHash()->getHex(),
                'prevBlock' => $bestHeader->getPrevBlock()->getHex(),
                'merkleRoot' => $bestHeader->getMerkleRoot()->getHex(),
                'nBits' => $bestHeader->getBits()->getInt(),
                'nTimestamp' => $bestHeader->getTimestamp(),
                'nNonce' => $bestHeader->getNonce()
            ],
            'best_block' => [
                'height' => $bestBlockIdx->getHeight(),
                'hash' => $bestBlockIdx->getHash()->getHex(),
                'prevBlock' => $bestBlockHeader->getPrevBlock()->getHex(),
                'merkleRoot' => $bestBlockHeader->getMerkleRoot()->getHex(),
                'nBits' => $bestBlockHeader->getBits()->getInt(),
                'nTimestamp' => $bestBlockHeader->getTimestamp(),
                'nNonce' => $bestBlockHeader->getNonce()
            ],
            'nChain' => $nChain
        ];

        return $info;
    }

    /**
     * @param NodeInterface $node
     * @return array
     */
    public function onChains(NodeInterface $node)
    {
        $chains = [];
        foreach ($node->chains()->getStates() as $state) {
            $chain = $state->getChain();
            $bestHeaderIdx = $chain->getIndex();
            $bestHeader = $bestHeaderIdx->getHeader();
            $bestBlockIdx = $state->getLastBlock();
            $bestBlockHeader = $bestBlockIdx->getHeader();

            $chains[] = [
                'height' => $bestHeaderIdx->getHeight(),
                'work' => $bestHeaderIdx->getWork(),
                'best_header' => [
                    'height' => $bestHeaderIdx->getHeight(),
                    'hash' => $bestHeaderIdx->getHash()->getHex(),
                    'prevBlock' => $bestHeader->getPrevBlock()->getHex(),
                    'merkleRoot' => $bestHeader->getMerkleRoot()->getHex(),
                    'nBits' => $bestHeader->getBits()->getInt(),
                    'nTimestamp' => $bestHeader->getTimestamp(),
                    'nNonce' => $bestHeader->getNonce()
                ],
                'best_block' => [
                    'height' => $bestBlockIdx->getHeight(),
                    'hash' => $bestBlockIdx->getHash()->getHex(),
                    'prevBlock' => $bestBlockHeader->getPrevBlock()->getHex(),
                    'merkleRoot' => $bestBlockHeader->getMerkleRoot()->getHex(),
                    'nBits' => $bestBlockHeader->getBits()->getInt(),
                    'nTimestamp' => $bestBlockHeader->getTimestamp(),
                    'nNonce' => $bestBlockHeader->getNonce()
                ]
            ];
        }

        return $chains;
    }
}
