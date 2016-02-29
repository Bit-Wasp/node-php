<?php

namespace BitWasp\Bitcoin\Node\Debug;


class DevNullDebug implements DebugInterface
{
    /**
     * @param string $event
     * @param array $params
     */
    public function log($event, array $params)
    {
        return;
    }
}