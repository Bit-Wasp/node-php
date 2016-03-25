<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;


use Pimple\Container;
use Pimple\ServiceProviderInterface;

class P2PInvServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $container['p2p.inv'] = function (Container $container) {
            return new P2PInvService($container);
        };
        
        $container['p2p.inv'];
    }
}