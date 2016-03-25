<?php

namespace BitWasp\Bitcoin\Node\Services\Utxos;

use BitWasp\Bitcoin\Node\NodeInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class UtxoServiceProvider implements ServiceProviderInterface
{

    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * UtxoServiceProvider constructor.
     * @param NodeInterface $node
     */
    public function __construct(NodeInterface $node)
    {
        $this->node = $node;
    }

    /**
     * @param Container $pimple
     */
    public function register(Container $pimple)
    {
        $pimple['utxos'] = function (Container $pimple) {
            return new UtxoService($this->node, $pimple);
        };
        
        $pimple['utxos'];
    }
}
