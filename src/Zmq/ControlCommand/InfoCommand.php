<?php

namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;


use BitWasp\Bitcoin\Node\NodeInterface;

class InfoCommand extends Command
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'info';
    }

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params = [])
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
}