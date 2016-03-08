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
    private $name;
    /**
     * @var string
     */
    private $description;

    /**
     * DbCommand constructor.
     * @param string $name
     * @param string $description
     */
    public function __construct($name, $description)
    {
        $this->name = $name;
        $this->description = $description;
        parent::__construct();
    }

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('db:'.$this->name)
            ->setDescription($this->description);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        call_user_func([new Db((new ConfigLoader())->load()), $this->name]);
        return 0;
    }
}