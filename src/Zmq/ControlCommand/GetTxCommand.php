<?php

namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;


use BitWasp\Bitcoin\Node\NodeInterface;

class GetTxCommand extends Command
{
    public function getName()
    {
        return 'gettx';
    }

    public function execute(NodeInterface $node, array $params = [])
    {
        if (!isset($params['txid'])) {
            return ['error' => 'Missing txid field'];
        }

        $chain = $node->chain()->getChain();
        try {
            $tx = $chain->fetchTransaction($node->txidx(), $params['txid']);

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