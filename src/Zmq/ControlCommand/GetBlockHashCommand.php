<?php

namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;

class GetBlockHashCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('getblockhash')
            ->setDescription('Returns the block hash for height')
            ->setParam('height', 'Block height');
    }

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params = [])
    {
        if (!isset($params['height'])) {
            throw new \RuntimeException('Missing height field');
        } else if (is_int($params['height'])) {
            throw new \RuntimeException('Invalid height');
        }

        $chain = $node->chain()->getChain();

        $hash = $chain->getHashFromHeight($params['height']);
        return ['hash'=>$hash->getHex()];
    }
}
