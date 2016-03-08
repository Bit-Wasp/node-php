<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;


use BitWasp\Bitcoin\Node\Config\ConfigLoader;
use BitWasp\Bitcoin\Node\Db;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DbCommand extends AbstractCommand
{
    /**
     * @var string
     */
    private $command;

    /**
     * DbCommand constructor.
     * @param string $name
     * @param string $description
     */
    public function __construct($name, $description)
    {
        parent::__construct();

        $this
            ->setName('db:'.$name)
            ->setDescription($description);

        $this->command = $name;
    }

    /**
     *
     */
    protected function configure()
    {

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        call_user_func([new Db((new ConfigLoader())->load()), $this->command]);
        return 0;
    }
}