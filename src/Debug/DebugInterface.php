<?php

namespace BitWasp\Bitcoin\Node\Debug;

interface DebugInterface
{
    /**
     * @param $event
     * @param array $params
     * @return void
     */
    public function log($event, array $params);
}