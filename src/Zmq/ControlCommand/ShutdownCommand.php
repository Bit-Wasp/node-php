<?php

namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Zmq\ScriptThreadControl;

class ShutdownCommand extends Command
{

    /**
     * @var ScriptThreadControl
     */
    private $threadControl;

    /**
     * ShutdownCommand constructor.
     * @param ScriptThreadControl $threadControl
     */
    public function __construct(ScriptThreadControl $threadControl)
    {
        $this->threadControl = $threadControl;
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
    public function execute(NodeInterface $node, array $params = [])
    {
        $this->threadControl->shutdown();
        $node->stop();

        return [
            'message' => 'Shutting down'
        ];
    }
}
