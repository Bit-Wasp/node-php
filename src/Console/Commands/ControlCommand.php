<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use BitWasp\Bitcoin\Node\UserControl\ControlCommand\CommandInterface;
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
     * NodeControlCommand constructor.
     * @param CommandInterface $command
     */
    public function __construct(CommandInterface $command)
    {
        $this->command = $command;
        parent::__construct();
    }

    /**
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
     *
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $context = new \ZMQContext();
        $push = $context->getSocket(\ZMQ::SOCKET_REQ);
        $push->connect('tcp://127.0.0.1:5560');

        $params = $this->getParams($input);

        $msg = json_encode([
            'cmd' => $this->command->getName(),
            'params' => $params
        ]);

        $push->send($msg);
        $response = $push->recv();
        $output->writeln($response);
        return 0;
    }
}
