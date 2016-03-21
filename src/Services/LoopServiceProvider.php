<?php

namespace BitWasp\Bitcoin\Node\Services;

use React\EventLoop\LoopInterface;

class LoopServiceProvider extends GenericInstanceServiceProvider
{
    /**
     * LoopServiceProvider constructor.
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        parent::__construct('loop', $loop);
    }
}
