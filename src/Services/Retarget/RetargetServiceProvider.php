<?php

namespace BitWasp\Bitcoin\Node\Services\Retarget;

use BitWasp\Bitcoin\Node\NodeInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class RetargetServiceProvider implements ServiceProviderInterface
{
    private $node;
    public function __construct(NodeInterface $node)
    {
        $this->node = $node;
    }

    public function register(Container $container)
    {
        $container['retarget'] = function (Container $container) {
            return new RetargetService($this->node, $container);
        };
    }
}
