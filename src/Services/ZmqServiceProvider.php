<?php

namespace BitWasp\Bitcoin\Node\Services;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use React\ZMQ\Context;

class ZmqServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $container['zmq'] = function (Container $container) {
            $loop = $container['loop'];
            return new Context($loop);
        };
    }
}
