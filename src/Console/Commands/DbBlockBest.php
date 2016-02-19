<?php

namespace BitWasp\Bitcoin\Node\Console\Commands\Db;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\Params;
use BitWasp\Bitcoin\Node\BitcoinNode;
use BitWasp\Bitcoin\Node\Config\ConfigLoader;
use BitWasp\Bitcoin\Node\Console\Commands\AbstractCommand;
use BitWasp\Bitcoin\Node\Db;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DbBlockBest extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('db:blockbest')
            ->setDescription('Remove the last block from the database');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = (new ConfigLoader())->load();
        $math = Bitcoin::getMath();
        $params = new Params($math);
        $loop = \React\EventLoop\Factory::create();

        $app = new BitcoinNode($params, $loop);
        $chain = $app->chain();

        $height = $chain->getLastBlock()->getHeight() - 1;
        $cache = $chain->bestBlocksCache();

        $hash = $cache->getHash($height);

        echo "Current tip: \n";
        echo " --   Hash : $height\n";
        echo " -- Height : ".$hash->getHex()."\n";
        //$app->db->eraseBlock($hash);
        echo "Back to: \n";
        echo " --   Hash : ".($height-1)."\n";
        echo " -- Height : ".$cache->getHash($height - 1)->getHex()."\n";
        //



    }
}
