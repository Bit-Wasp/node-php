<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use BitWasp\Bitcoin\Node\StorageProvider;
use Packaged\Config\ConfigProviderInterface;
use Symfony\Component\Console\Command\Command;

abstract class AbstractCommand extends Command
{

    /**
     * @var string
     */
    protected $configFile = 'bitcoin.ini';

    /**
     * @var string
     */
    protected $defaultFolder = '.bitcoinphp';

    /**
     * @var string
     */
    protected $defaultRedisHost = '127.0.0.1';

    /**
     * @param string|null $file
     * @return \Packaged\Config\Provider\Ini\IniConfigProvider
     */
    public function loadConfig($file = null)
    {
        if (is_null($file)) {
            $file = getenv("HOME") . "/" . $this->defaultFolder . "/" . $this->configFile;
        }

        $configProvider = new \Packaged\Config\Provider\Ini\IniConfigProvider();
        $configProvider->loadFile($file);

        return $configProvider;
    }
}