<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmonier_sylius_registration_approval');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('channels')
                    ->info('Codes of the channels whose registrations an administrator validates. Empty (default): no channel, the plugin changes nothing.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
