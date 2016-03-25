<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;


use Pimple\Container;
use Pimple\ServiceProviderInterface;

class P2PPingServiceProvider implements ServiceProviderInterface
{

    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $container['p2p.ping'] = function (Container $container) {
            $pingService = new P2PPingService($container);

            return $pingService;
        };

        $container['p2p.ping'];
    }
}