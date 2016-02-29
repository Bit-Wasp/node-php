<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Node\Services\Debug\DebugInterface;

class DebugServiceProvider extends GenericInstanceServiceProvider
{
    /**
     * DebugServiceProvider constructor.
     * @param DebugInterface|null $debug
     */
    public function __construct(DebugInterface $debug)
    {
        parent::__construct('debug', $debug);
    }
}
