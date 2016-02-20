<?php

namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;

use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;

abstract class Command implements CommandInterface
{
    /**
     * @param BlockIndexInterface $index
     * @return array
     */
    public function convertIndexToArray(BlockIndexInterface $index)
    {
        $header = $index->getHeader();

        return [
            'height' => $index->getHeight(),
            'work' => $index->getWork(),
            'header' => [
                'height' => $index->getHeight(),
                'hash' => $index->getHash()->getHex(),
                'prevBlock' => $header->getPrevBlock()->getHex(),
                'merkleRoot' => $header->getMerkleRoot()->getHex(),
                'nBits' => $header->getBits()->getInt(),
                'nTimestamp' => $header->getTimestamp(),
                'nNonce' => $header->getNonce()
            ],
        ];
    }
}