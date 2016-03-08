<?php

namespace BitWasp\Bitcoin\Node\Console\Commands\Config;

use BitWasp\Bitcoin\Node\Console\Commands\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigDefault extends AbstractCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('config:default')
            ->setDescription('Print a blank configuration file');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->write('[db]
# SQL configuration.
# The following 5 values are required:
driver=mysql
host=
username=
password=
database=

# General Configuration
[config]

# Should the client listen for incoming connections? []
listen=0
txrelay=0
daemon=1
download_blocks=1

# Enable websocket for debugging [default: false]
websocket=0

');
        return 0;
    }
}
