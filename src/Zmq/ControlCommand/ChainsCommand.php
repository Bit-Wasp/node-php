<?php


namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;


use BitWasp\Bitcoin\Node\NodeInterface;

class ChainsCommand extends Command
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'chains';
    }

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params = [])
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