<?php

namespace BitWasp\Bitcoin\Node\Services\Utxos;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain\BlockData;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\NodeInterface;
use Pimple\Container;

class UtxoService
{
    public function __construct(NodeInterface $node, Container $container)
    {
        $node->blocks()->on('block', function (ChainStateInterface $chainState, BlockInterface $block, BlockData $blockData) use ($node) {
            $utxos = $node->utxos();
            $utxos->update($chainState, $block, $blockData);
        });
    }
}
