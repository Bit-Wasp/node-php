<?php

namespace BitWasp\Bitcoin\Node\Services\Debug;

interface DebugInterface
{
    /**
     * @param $event
     * @param array $params
     * @return void
     */
    public function log($event, array $params);
}
