<?php


namespace BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand;

use BitWasp\Bitcoin\Node\NodeInterface;

class ChainsCommand extends Command
{
    public function configure()
    {
        $this
            ->setName('chains')
            ->setDescription('Returns information about chains tracked by the node');
    }

    /**
     * @param NodeInterface $node
     * @param array $params
     * @return array
     */
    public function execute(NodeInterface $node, array $params)
    {
        $chains = [];
        foreach ($node->chains()->getStates() as $state) {
            $chain = $state->getChain();
            $bestHeaderIdx = $chain->getIndex();
            $bestBlockIdx = $state->getLastBlock();

            $chains[] = [
                'best_header' => $this->convertIndexToArray($bestHeaderIdx),
                'best_block' => $this->convertIndexToArray($bestBlockIdx)
            ];
        }

        return $chains;
    }
}
