<?php

namespace BitWasp\Bitcoin\Node\Console;

use BitWasp\Bitcoin\Node\Console\Commands\Db\DbBlockBest;
use BitWasp\Bitcoin\Node\Console\Commands\Db\DbBlocksReset;
use BitWasp\Bitcoin\Node\Console\Commands\Db\DbPopBlock;
use BitWasp\Bitcoin\Node\Console\Commands\Db\DbReset;
use BitWasp\Bitcoin\Node\Console\Commands\Db\DbWipe;
use BitWasp\Bitcoin\Node\Console\Commands\Node\NodeChains;
use BitWasp\Bitcoin\Node\Console\Commands\Node\NodeInfo;
use BitWasp\Bitcoin\Node\Console\Commands\Node\NodeTx;
use BitWasp\Bitcoin\Node\Console\Commands\PrintConfig;
use BitWasp\Bitcoin\Node\Console\Commands\ScriptWorker;
use BitWasp\Bitcoin\Node\Console\Commands\SelfTestNodeCommand;
use BitWasp\Bitcoin\Node\Console\Commands\Node\NodeStart;
use BitWasp\Bitcoin\Node\Console\Commands\Node\NodeStop;
use BitWasp\Bitcoin\Node\Console\Commands\TestCommand;
use BitWasp\Bitcoin\Node\Console\Commands\Node\NodeWatch;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new DbReset();
        $commands[] = new DbWipe();
        $commands[] = new DbBlocksReset();
        $commands[] = new NodeStart();
        $commands[] = new NodeStop();
        $commands[] = new NodeWatch();
        $commands[] = new NodeInfo();
        $commands[] = new NodeChains();
        $commands[] = new NodeTx();
        $commands[] = new DbPopBlock();

        $commands[] = new SelfTestNodeCommand();
        $commands[] = new PrintConfig();
        $commands[] = new ScriptWorker();
        $commands[] = new TestCommand();
        return $commands;
    }
}
