<?php

namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Buffertools\Buffer;

class GetTxCommand extends Command
{
    protected function configure()
    {
        $this->setName('gettx')
            ->setDescription('Return information about a transaction')
            ->setParam('txid', 'Transaction ID');
    }

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     *
     */
    public function execute(NodeInterface $node, array $params = [])
    {
        if (!isset($params['txid'])) {
            return ['error' => 'Missing txid field'];
        }

        $chain = $node->chain()->getChain();
        try {
            $txid = Buffer::hex($params['txid'], 32);
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
