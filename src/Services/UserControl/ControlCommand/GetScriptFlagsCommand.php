<?php

namespace BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand;

use BitWasp\Bitcoin\Node\Index\Validation\Forks;
use BitWasp\Bitcoin\Node\NodeInterface;

class GetScriptFlagsCommand extends Command
{
    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params)
    {
        $chain = $node->chain();

        $blkIdx = $node->blocks();

        /**
         * @var Forks $forks
         */
        list(, $forks) = $blkIdx->prepareForks($chain, $chain->validBlocks()->getIndex());

        return [
            'flags' => $forks->getFlags(),
            'details' => $forks->toArray(),
        ];
    }

    protected function configure()
    {
        $this
            ->setName('getscriptflags')
            ->setDescription('Returns the current script verification flags')
        ;
    }
}
