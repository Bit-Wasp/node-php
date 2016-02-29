<?php

namespace BitWasp\Bitcoin\Node\Services;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use React\EventLoop\LoopInterface;
use React\ZMQ\Context;

class ZmqServiceProvider implements ServiceProviderInterface
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * ZmqServiceProvider constructor.
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $container['zmq'] = function () {
            return new Context($this->loop);
        };
    }
}
