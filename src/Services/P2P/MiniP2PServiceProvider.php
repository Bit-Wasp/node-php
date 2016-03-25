<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;

use BitWasp\Bitcoin\Networking\Peer\ConnectionParams;
use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\P2P\State\Peers;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerStateCollection;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class MiniP2PServiceProvider implements ServiceProviderInterface
{
    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * P2PServiceProvider constructor.
     * @param NodeInterface $node
     */
    public function __construct(NodeInterface $node)
    {
        $this->node = $node;
    }

    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $container['p2p.states'] = function () {
            return new PeerStateCollection();
        };

        $container['p2p.outbound'] = function () {
            return new Peers();
        };

        $container['p2p.inbound'] = function () {
            return new Peers();
        };

        $container['p2p.params'] = function () {
            return new ConnectionParams();
        };

        $container['p2p'] = function (Container $container) {
            return new MiniP2PService($container);
        };
    }
}
