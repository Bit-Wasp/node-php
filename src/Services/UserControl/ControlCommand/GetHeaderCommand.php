<?php

namespace BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Buffertools\Buffer;

class GetHeaderCommand extends Command
{
    const PARAM_HASH = 'hash';

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params)
    {
        if (strlen($params[self::PARAM_HASH]) !== 64) {
            throw new \RuntimeException('Invalid hash');
        }

        $index = $node->chain()->fetchIndex(Buffer::hex($params[self::PARAM_HASH]));

        return [
            'header' => $this->convertIndexToArray($index)
        ];
    }

    protected function configure()
    {
        $this
            ->setName('getheader')
            ->setDescription('Return the requested block header')
            ->setParam(self::PARAM_HASH, 'Block hash');
    }
}
