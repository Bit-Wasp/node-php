<?php

namespace BitWasp\Bitcoin\Node\State;


class NodeState extends AbstractState
{
    /**
     * @var array
     */
    protected static $defaults = [];

    /**
     * @return static
     */
    public static function create()
    {
        $state = new static;
        foreach (static::$defaults as $key => $value) {
            $state->save($key, $value);
        }
        return $state;
    }
}