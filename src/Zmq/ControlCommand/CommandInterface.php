<?php

namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;

interface CommandInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return array
     */
    public function getParams();

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params = []);
}
