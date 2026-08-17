<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines the configuration tree for nowo_hot_reload.
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_hot_reload';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->info('Master switch. When false, nothing is injected even if FRANKENPHP_HOT_RELOAD is set.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('auto_inject')
                    ->info('When true, HotReloadResponseSubscriber injects assets into HTML responses.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('require_frankenphp_env')
                    ->info('When true (default), inject only if FRANKENPHP_HOT_RELOAD is set or mercure_url is configured.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('mercure_url')
                    ->info('Optional Mercure hub URL. When null, uses $_SERVER[\'FRANKENPHP_HOT_RELOAD\'] when present.')
                    ->defaultNull()
                ->end()
                ->booleanNode('idiomorph')
                    ->info('When true, include Idiomorph for DOM morphing instead of a full page reload.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('idiomorph_script_url')
                    ->info('URL of the Idiomorph script (classic script tag).')
                    ->defaultValue('https://cdn.jsdelivr.net/npm/idiomorph')
                ->end()
                ->scalarNode('hot_reload_script_url')
                    ->info('URL of the frankenphp-hot-reload ESM module.')
                    ->defaultValue('https://cdn.jsdelivr.net/npm/frankenphp-hot-reload/+esm')
                ->end()
                ->arrayNode('preserve_selectors')
                    ->info('CSS selectors for elements that should receive data-frankenphp-hot-reload-preserve (e.g. Symfony Web Debug Toolbar).')
                    ->scalarPrototype()->end()
                    ->defaultValue(['#sfwdt', '.sf-toolbar'])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
