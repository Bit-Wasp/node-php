<?php

namespace BitWasp\Bitcoin\Node\Services\P2P;

use BitWasp\Bitcoin\Networking\Peer\ConnectionParams;
use BitWasp\Bitcoin\Networking\Factory as NetworkFactory;
use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\P2P\State\Peers;
use BitWasp\Bitcoin\Node\Services\P2P\State\PeerStateCollection;
use Packaged\Config\ConfigProviderInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class P2PServiceProvider implements ServiceProviderInterface
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

        $container['p2p.factory'] = function (Container $container) {
            $factory = new NetworkFactory($container['loop'], $container['network.params.addr']);
            $factory->setSettings($container['network.params.p2p']);
            return $factory;
        };

        $container['p2p.params'] = $container->factory(function (Container $container) {
            /** @var ConfigProviderInterface $config */
            $config = $container['config'];
            $params =  new ConnectionParams();
            $params->requestTxRelay((bool)$config->getItem('config', 'tx_relay', false));
            return $params;
        });

        $container['p2p'] = function (Container $container) {
            return new P2PService($container);
        };
    }
}
