<?php

namespace BitWasp\Bitcoin\Node\Console\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractNodeClient extends AbstractCommand
{
    /**
     * @var string
     */
    protected $description;

    /**
     * @return string
     */
    abstract public function getNodeCommand();

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

    protected function getParams(InputInterface $input)
    {
        return [];
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
        if (count($params) > 0) {
            $msg = json_encode([
                'cmd' => $this->getNodeCommand(),
                'params' => $params
            ]);
        } else {
            $msg = $this->getNodeCommand();
        }

        $push->send($msg);
        $response = $push->recv();
        $output->writeln($response);
        return 0;
    }
}
