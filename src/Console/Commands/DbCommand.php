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
    private $dbName;

    /**
     * @var string
     */
    private $dbDesc;

    /**
     * DbCommand constructor.
     * @param string $name
     * @param string $description
     */
    public function __construct($name, $description)
    {
        $this->dbName = $name;
        $this->dbDesc = $description;
        parent::__construct();
    }

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('db:' . $this->dbName)
            ->setDescription($this->dbDesc);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        call_user_func([Db::create((new ConfigLoader())->load()), $this->dbName]);
        return 0;
    }
}
