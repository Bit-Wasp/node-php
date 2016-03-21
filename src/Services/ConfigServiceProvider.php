<?php

namespace BitWasp\Bitcoin\Node\Services;

use Packaged\Config\ConfigProviderInterface;

class ConfigServiceProvider extends GenericInstanceServiceProvider
{
    /**
     * ConfigServiceProvider constructor.
     * @param ConfigProviderInterface $config
     */
    public function __construct(ConfigProviderInterface $config)
    {
        parent::__construct('config', $config);
    }
}
