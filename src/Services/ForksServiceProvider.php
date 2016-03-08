<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\Forks;
use BitWasp\Bitcoin\Node\Chain\HeadersBatch;
use BitWasp\Bitcoin\Node\DbInterface;
use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\Debug\DebugInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ForksServiceProvider implements ServiceProviderInterface
{
    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * @var ParamsInterface
     */
    private $params;

    /**
     * ForksServiceProvider constructor.
     * @param NodeInterface $node
     */
    public function __construct(NodeInterface $node, ParamsInterface $params)
    {
        $this->node = $node;
        $this->params = $params;
    }

    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $headers = $this->node->headers();
        $headers->on('headers', function (HeadersBatch $batch) use ($container) {

            /** @var DbInterface $db */
            $db = $container['db'];

            /** @var DebugInterface $debug */
            $debug = $container['debug'];
            if (count($batch->getIndices()) > 0) {
                $first = $batch->getIndices()[0];
                $prevIndex = $db->fetchIndex($first->getHeader()->getPrevBlock());
                $versionInfo = $db->findSuperMajorityInfoByHash($prevIndex->getHash());
                $forks = new Forks($this->params, $prevIndex, $versionInfo);
                $first = $forks->toArray();
                $changes = [];
                foreach ($batch->getIndices() as $index) {
                    $forks->next($index);
                    $new = $forks->toArray();
                    if ($first !== $new) {
                        $changes[] = [$index, $first, $new];
                        $first = $new;
                    }
                }

                foreach ($changes as $change) {
                    /** @var BlockIndexInterface $index */
                    list ($index, $first, $features) = $change;
                    $debug->log('fork.new', ['hash' => $index->getHash()->getHex(), 'height' => $index->getHeight(), 'old' => $first, 'features' => $features]);
                }
            }

        });

    }
}
