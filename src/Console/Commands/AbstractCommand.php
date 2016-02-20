<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

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
}
