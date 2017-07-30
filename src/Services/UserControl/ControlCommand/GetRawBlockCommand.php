<?php

namespace BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Buffertools\Buffer;

class GetRawBlockCommand extends Command
{
    const PARAM_HASH = 'hash';

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params)
    {
        $chain = $node->chain();

        if (strlen($params[self::PARAM_HASH]) != 64) {
            throw new \RuntimeException("Invalid value for hash");
        }

        try {
            $hash = Buffer::hex($params[self::PARAM_HASH],32);
        } catch (\Exception $e) {
            throw new \RuntimeException("Invalid value for hash");
        }

        if (!$chain->containsHash($hash)) {
            throw new \RuntimeException("Chain does not contain hash\n");
        }

        $access = $node->chains()->access($chain);
        $block = $access->fetchBlock($hash);

        return [
            'block' => $block->getHex(),
        ];
    }

    protected function configure()
    {
        $this
            ->setName('getrawblock')
            ->setDescription('Returns the block hex')
            ->setParam(self::PARAM_HASH, 'Block hash');
    }
}
