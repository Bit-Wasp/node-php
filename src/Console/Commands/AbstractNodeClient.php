<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractNodeClient extends AbstractCommand
{
    protected $description;

    /**
     * @return string
     */
    abstract public function getNodeCommand();

    /**
     * @param InputInterface $input
     * @return array
     */
    public function getArguments(InputInterface $input)
    {
        return [];
    }

    /**
     *
     */
    protected function configure()
    {
        $this->setName('node:' . $this->getNodeCommand());
        if (null !== $this->description) {
            $this->setDescription($this->description);
        }
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
        $push->send($this->getNodeCommand());
        $response = $push->recv();
        $output->writeln($response);
        return 0;
    }
}