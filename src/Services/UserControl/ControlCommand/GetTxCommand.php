<?php

namespace BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Buffertools\Buffer;

class GetTxCommand extends Command
{
    const PARAM_TXID = 'txid';

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params)
    {
        if (strlen($params[self::PARAM_TXID]) !== 64) {
            throw new \RuntimeException('Invalid txid field');
        }

        $txid = Buffer::hex($params[self::PARAM_TXID], 32);
        $tx = $node->chain()->fetchTransaction($node->transactions(), $txid);

        return [
            'tx' => $this->convertTransactionToArray($tx)
        ];
    }

    protected function configure()
    {
        $this->setName('gettx')
            ->setDescription('Return information about a transaction')
            ->setParam(self::PARAM_TXID, 'Transaction ID');
    }
}
