<?php

namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;


use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Buffertools\Buffer;

class GetHeaderCommand extends Command
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'getheader';
    }

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params = [])
    {
        if (!isset($params['hash'])) {
            throw new \RuntimeException('Missing txid field');
        } else if (strlen($params['hash']) !== 64) {
            throw new \RuntimeException('Invalid txid'.strlen($params['hash']));
        }

        $chain = $node->chain()->getChain();

        $index = $chain->fetchIndex(Buffer::hex($params['hash']));
        return $this->convertIndexToArray($index);
    }
}