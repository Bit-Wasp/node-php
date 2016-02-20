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
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params = []);
}