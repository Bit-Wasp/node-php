<?php

namespace BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand;

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
    public function execute(NodeInterface $node, array $params);

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function run(NodeInterface $node, array $params);
}
