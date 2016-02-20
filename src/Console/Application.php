<?php

namespace BitWasp\Bitcoin\Node\Console;

use BitWasp\Bitcoin\Node\Console\Commands\Db\DbBlocksReset;
use BitWasp\Bitcoin\Node\Console\Commands\Db\DbReset;
use BitWasp\Bitcoin\Node\Console\Commands\Db\DbWipe;
use BitWasp\Bitcoin\Node\Console\Commands\ControlCommand;
use BitWasp\Bitcoin\Node\Console\Commands\WebSocketCommand;
use BitWasp\Bitcoin\Node\Console\Commands\PrintConfig;
use BitWasp\Bitcoin\Node\Console\Commands\StartCommand;
use BitWasp\Bitcoin\Node\Console\Commands\StopCommand;
use BitWasp\Bitcoin\Node\Console\Commands\WatchCommand;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\ChainsCommand;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\GetBlockHashCommand;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\GetHeaderCommand;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\GetTxCommand;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\InfoCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new DbReset();
        $commands[] = new DbWipe();
        $commands[] = new DbBlocksReset();

        $commands[] = new StartCommand();
        $commands[] = new StopCommand();
        $commands[] = new WatchCommand();
        $commands[] = new WebSocketCommand();

        $commands[] = new ControlCommand(new InfoCommand());
        $commands[] = new ControlCommand(new ChainsCommand());
        $commands[] = new ControlCommand(new GetTxCommand());
        $commands[] = new ControlCommand(new GetHeaderCommand());
        $commands[] = new ControlCommand(new GetBlockHashCommand());

        //$commands[] = new SelfTestNodeCommand();
        $commands[] = new PrintConfig();
        return $commands;
    }
}
