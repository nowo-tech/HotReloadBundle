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

    public const DEFAULT_IDIOMORPH_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/idiomorph@0.7.4';

    public const DEFAULT_HOT_RELOAD_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm';

    /** @var list<string> */
    public const DEFAULT_PRESERVE_SELECTORS = ['[id^="sfwdt"]', '.sf-toolbar', '.sf-minitoolbar'];

    /** @var list<string> */
    public const DEFAULT_CSP_SCRIPT_SRC_HOSTS = ['https://cdn.jsdelivr.net'];

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
                ->booleanNode('allow_production')
                    ->info('When false (default), enabling this bundle in the prod environment raises InvalidConfigurationException.')
                    ->defaultFalse()
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
                    ->info('URL of the Idiomorph script (classic script tag). Prefer a version-pinned CDN URL.')
                    ->defaultValue(self::DEFAULT_IDIOMORPH_SCRIPT_URL)
                ->end()
                ->scalarNode('hot_reload_script_url')
                    ->info('URL of the frankenphp-hot-reload ESM module. Prefer a version-pinned CDN URL.')
                    ->defaultValue(self::DEFAULT_HOT_RELOAD_SCRIPT_URL)
                ->end()
                ->arrayNode('preserve_selectors')
                    ->info('CSS selectors for elements that should receive data-frankenphp-hot-reload-preserve (e.g. Symfony Web Debug Toolbar).')
                    ->scalarPrototype()->end()
                    ->defaultValue(self::DEFAULT_PRESERVE_SELECTORS)
                ->end()
                ->booleanNode('preserve_observe')
                    ->info('When true, the preserve boot script also uses MutationObserver for late-injected toolbar nodes.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('csp_nonce_request_attribute')
                    ->info('Request attribute name that holds the CSP nonce (e.g. "_csp_nonce"). Applied to the inline preserve boot script.')
                    ->defaultNull()
                ->end()
                ->booleanNode('csp_augment_script_src')
                    ->info('When true, append CDN hosts to an existing Content-Security-Policy script-src on the response after injection.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('csp_script_src_hosts')
                    ->info('Hosts appended to script-src when csp_augment_script_src is true. Empty = derive origins from script URLs.')
                    ->scalarPrototype()->end()
                    ->defaultValue(self::DEFAULT_CSP_SCRIPT_SRC_HOSTS)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
