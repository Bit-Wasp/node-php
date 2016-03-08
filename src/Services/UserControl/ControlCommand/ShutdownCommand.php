<?php

namespace BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand;

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
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params)
    {
        $this->loop->stop();
        $node->stop();

        return [
            'message' => 'Shutting down'
        ];
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
}
