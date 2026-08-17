<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Twig\Extension\AbstractExtension;

/**
 * Loads bundle configuration and services for FrankenPHP Hot Reload integration.
 */
final class HotReloadExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $container->setParameter('nowo.hot_reload.enabled', $config['enabled']);
        $container->setParameter('nowo.hot_reload.auto_inject', $config['auto_inject']);
        $container->setParameter('nowo.hot_reload.require_frankenphp_env', $config['require_frankenphp_env']);
        $container->setParameter('nowo.hot_reload.mercure_url', $config['mercure_url']);
        $container->setParameter('nowo.hot_reload.idiomorph', $config['idiomorph']);
        $container->setParameter('nowo.hot_reload.idiomorph_script_url', $config['idiomorph_script_url']);
        $container->setParameter('nowo.hot_reload.hot_reload_script_url', $config['hot_reload_script_url']);
        $container->setParameter('nowo.hot_reload.preserve_selectors', $config['preserve_selectors']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if (class_exists(AbstractExtension::class)) {
            $loader->load('twig.yaml');
        }
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
