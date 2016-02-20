<?php

namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;

class ShutdownCommand extends Command
{

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
    public function execute(NodeInterface $node, array $params = [])
    {
        $node->stop();

        return [
            'message' => 'Shutting down'
        ];
    }
}
