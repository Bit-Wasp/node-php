<?php

namespace BitWasp\Bitcoin\Node\Config;

use Packaged\Config\ConfigProviderInterface;
use Packaged\Config\Provider\Ini\IniConfigProvider;

class ConfigLoader
{
    /**
     * @var string
     */
    protected $defaultLocation = '/.phpnode/bitcoin.ini';

    /**
     * @var string
     */
    protected $location;

    /**
     * ConfigLoader constructor.
     * @param string|null $file
     */
    public function __construct($file = null)
    {
        if ($file === null) {
            $file = getenv('HOME') . $this->defaultLocation;
        }

        if ($file !== null && !file_exists($file)) {
            throw new \RuntimeException('Config file does not exist: ' . $file);
        }

        $this->location = $file;
    }

    /**
     * @return ConfigProviderInterface
     */
    public function load()
    {
        $config = new IniConfigProvider();
        $config->loadFile($this->location);
        return $config;
    }

}