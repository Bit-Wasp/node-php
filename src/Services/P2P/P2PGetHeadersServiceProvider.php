<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;


use BitWasp\Bitcoin\Node\NodeInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class P2PGetHeadersServiceProvider implements ServiceProviderInterface
{
    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * P2PGetHeadersServiceProvider constructor.
     * @param NodeInterface $node
     */
    public function __construct(NodeInterface $node)
    {
        $this->node = $node;
    }

    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $container['p2p.getheaders'] = function (Container $container) {
            return new P2PGetHeadersService($this->node, $container);
        };
        $container['p2p.getheaders'];
    }
}