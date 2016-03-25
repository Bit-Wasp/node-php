<?php

namespace BitWasp\Bitcoin\Node\Services\P2P\Headers;


use BitWasp\Bitcoin\Node\NodeInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class P2PHeadersServiceProvider implements ServiceProviderInterface
{
    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * P2PHeadersServiceProvider constructor.
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
        $container['p2p.headers'] = function (Container $container) {
            return new P2PHeadersService($this->node, $container);
        };
        $container['p2p.headers'];
    }
}