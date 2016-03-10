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

## Listening server - allows the node to accept inbound connections
listen=0

## Daemon - whether process should fork in the background
# defaults to off
daemon=1

## Download blocks - sync blocks in addition to headers
# defaults to on
download_blocks=1

## Check Signatures - whether any script validation should be performed
# defaults to on
check_signatures=1

## Transaction relay - whether the node should request transaction INV messages
# defaults to off
txrelay=0

## Web socket - Enable WAMP server for logs and commands
# defaults to off
websocket=0

');
        return 0;
    }
}
