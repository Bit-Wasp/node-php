<?php

namespace BitWasp\Bitcoin\Node\Zmq;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Buffertools\Buffer;
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

            $decoded = json_decode($e, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($decoded['cmd']) && isset($decoded['params']) && is_array($decoded['params'])) {
                    $c = $decoded['cmd'];
                    $params = $decoded['params'];
                    if ($c === 'tx') {
                        if (isset($params['txid']) && strlen($params['txid']) === 64) {
                            $result = $this->onTransaction($node, Buffer::hex($params['txid'], 32));
                        } else {
                            $result = ['error' => 'Missing or incorrect transaction id'];
                        }
                    }
                }
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

    public function onTransaction(NodeInterface $node, Buffer $txid)
    {
        $chain = $node->chain()->getChain();
        try {
            $tx = $chain->fetchTransaction($node->txidx(), $txid);

            $inputs = [];
            foreach ($tx->getInputs() as $in) {
                $outpoint = $in->getOutPoint();
                $inputs[] = [
                    'txid' => $outpoint->getTxId()->getHex(),
                    'vout' => $outpoint->getVout(),
                    'scriptSig' => $in->getScript()->getHex(),
                    'sequence' => $in->getSequence()
                ];
            }

            $outputs = [];
            foreach ($tx->getOutputs() as $out) {
                $outputs[] = [
                    'value' => $out->getValue(),
                    'scriptPubKey' => $out->getScript()->getHex()
                ];
            }

            $buf = $tx->getBuffer()->getBinary();
            return [
                'hash' => $tx->getTxId()->getHex(),
                'version' => $tx->getVersion(),
                'inputs' => $inputs,
                'outputs' => $outputs,
                'nLockTime' => $tx->getLockTime(),
                'raw' => bin2hex($buf),
                'size' => strlen($buf)
            ];

        } catch (\Exception $e) {
            return [
                'error' => 'Transaction not found' . $e->getMessage() . "\n" . $e->getTraceAsString()
            ];
        }
    }
}
