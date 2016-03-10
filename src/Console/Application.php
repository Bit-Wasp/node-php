<?php

namespace BitWasp\Bitcoin\Node\Console;

use BitWasp\Bitcoin\Node\Console\Commands\Config\ConfigDefault;
use BitWasp\Bitcoin\Node\Console\Commands\ControlCommand;
use BitWasp\Bitcoin\Node\Console\Commands\DbCommand;
use BitWasp\Bitcoin\Node\Console\Commands\StartCommand;
use BitWasp\Bitcoin\Node\Console\Commands\StopCommand;
use BitWasp\Bitcoin\Node\Console\Commands\WatchCommand;
use BitWasp\Bitcoin\Node\Console\Commands\WebSocketCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\ChainsCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\GetBlockHashCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\GetHeaderCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\GetTxCommand;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\InfoCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        $commands[] = new DbCommand('resetBlocksOnly', 'Removes only block and transaction information');
        $commands[] = new DbCommand('reset', 'Deletes everything from the database');
        $commands[] = new DbCommand('wipe', 'Deletes everything - INCLUDING the database');

        $commands[] = new StartCommand();
        $commands[] = new StopCommand();
        $commands[] = new WatchCommand();
        $commands[] = new WebSocketCommand();

        $commands[] = new ControlCommand(new InfoCommand());
        $commands[] = new ControlCommand(new ChainsCommand());
        $commands[] = new ControlCommand(new GetTxCommand());
        $commands[] = new ControlCommand(new GetHeaderCommand());
        $commands[] = new ControlCommand(new GetBlockHashCommand());

        $commands[] = new ConfigDefault();
        return $commands;
    }
}
