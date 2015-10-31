<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;


use BitWasp\Bitcoin\Node\Db;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Packaged\Config\Provider\Ini\IniConfigProvider;

class DbReset extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('db:reset')
            ->setDescription('Wipe everything - careful now');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = getenv('HOME') . '/.bitcoinphp/bitcoin.ini';
        $config = new IniConfigProvider();
        try {
            $config->loadFile($file);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to load config file');
        }

        $db = new Db($config);
        $db->reset();
    }
}