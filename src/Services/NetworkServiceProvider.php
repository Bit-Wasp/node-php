<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\Params;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Networking\Settings\MainnetSettings;
use BitWasp\Bitcoin\Networking\Settings\RegtestSettings;
use BitWasp\Bitcoin\Node\Params\RegtestParams;
use Packaged\Config\ConfigProviderInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class NetworkServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        /** @var ConfigProviderInterface $config */
        $config = $container['config'];

        $network = $config->getItem('network', 'name', null);
        if (null === $network) {
            $network = 'bitcoin-mainnet';
        }

        switch($network) {
            default:
                throw new \RuntimeException("Unsupported network: {$network}");
            case 'bitcoin-mainnet':
                $network = NetworkFactory::bitcoin();
                $params = new Params(Bitcoin::getMath());
                $p2pSettings = new MainnetSettings();
                break;
            case 'bitcoin-regtest':
                $network = NetworkFactory::bitcoinRegtest();
                $params = new RegtestParams(Bitcoin::getMath());
                $p2pSettings = new RegtestSettings();
                break;
        }

        $container['network.params.addr'] = $network;
        $container['network.params.chain'] = $params;
        $container['network.params.p2p'] = $p2pSettings;
    }
}