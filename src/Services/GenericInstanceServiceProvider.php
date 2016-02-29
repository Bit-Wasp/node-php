<?php

namespace BitWasp\Bitcoin\Node\Services;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class GenericInstanceServiceProvider implements ServiceProviderInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var
     */
    private $instance;

    /**
     * GenericInstanceServiceProvider constructor.
     * @param string $name
     * @param object $instance
     */
    public function __construct($name, $instance)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException('GenericInstanceService name must be a string');
        }

        if (!is_object($instance)) {
            throw new \InvalidArgumentException('GenericInstanceService instance must be a class');
        }

        $this->name = $name;
        $this->instance = $instance;
    }

    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $container[$this->name] = function () {
            return $this->instance;
        };
    }
}
