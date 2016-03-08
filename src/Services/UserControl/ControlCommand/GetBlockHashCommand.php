<?php

namespace BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;

class GetBlockHashCommand extends Command
{
    const PARAM_HEIGHT = 'height';

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params)
    {
        if (!is_int($params[self::PARAM_HEIGHT])) {
            throw new \RuntimeException('Invalid height');
        }

        $chain = $node->chain()->getChain();
        $hash = $chain->getHashFromHeight($params[self::PARAM_HEIGHT]);

        return [
            'hash' => $hash->getHex()
        ];
    }

    protected function configure()
    {
        $this
            ->setName('getblockhash')
            ->setDescription('Returns the block hash for height')
            ->setParam(self::PARAM_HEIGHT, 'Block height');
    }
}
