<?php

namespace BitWasp\Bitcoin\Node\UserControl\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;
use React\EventLoop\LoopInterface;

class ShutdownCommand extends Command
{

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * ShutdownCommand constructor.
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
        parent::__construct();
    }

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('stop')
            ->setDescription('Shuts down the node');
    }

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params)
    {
        $node->stop();
        $this->loop->stop();

        return [
            'message' => 'Shutting down'
        ];
    }
}
