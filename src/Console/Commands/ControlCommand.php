<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\CommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ControlCommand extends AbstractCommand
{
    /**
     * @var CommandInterface
     */
    private $command;

    /**
     * Wraps any CommandInterface to expose it as a console command
     *
     * NodeControlCommand constructor.
     * @param CommandInterface $command
     */
    public function __construct(CommandInterface $command)
    {
        $this->command = $command;
        parent::__construct();
    }

    /**
     * Loads the arguments required by this command
     *
     * @param InputInterface $input
     * @return array
     */
    protected function getParams(InputInterface $input)
    {
        $params = [];
        foreach ($this->command->getParams() as $param => $description) {
            $params[$param] = $input->getArgument($param);
        }

        return $params;
    }

    /**
     * Configures the command name, arguments, and description from
     * the CommandInterface
     */
    protected function configure()
    {
        $this->setName('node:' . $this->command->getName());
        foreach ($this->command->getParams() as $param => $desc) {
            $this->addArgument($param, InputArgument::REQUIRED, $desc);
        }

        $this->setDescription($this->command->getDescription());
    }

    /**
     * Execute the command by requesting it over the ZMQ socket
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $context = new \ZMQContext();
        $push = $context->getSocket(\ZMQ::SOCKET_REQ);
        $push->connect('tcp://127.0.0.1:5560');
        $push->send(json_encode(['cmd' => $this->command->getName(), 'params' => $this->getParams($input)]));

        $response = $push->recv();
        $output->writeln($response);
        return 0;
    }
}
