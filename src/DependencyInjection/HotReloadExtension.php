<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Twig\Extension\AbstractExtension;

use function class_exists;
use function is_string;

/**
 * Loads bundle configuration and services for FrankenPHP Hot Reload integration.
 */
final class HotReloadExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $rawEnvironment = $container->hasParameter('kernel.environment')
            ? $container->getParameter('kernel.environment')
            : '';
        $environment = is_string($rawEnvironment) ? $rawEnvironment : '';

        if (
            $config['enabled']
            && $environment === 'prod'
            && $config['allow_production'] !== true
        ) {
            throw new InvalidConfigurationException('nowo_hot_reload.enabled cannot be true in the "prod" environment unless allow_production is true. Register NowoHotReloadBundle for dev/test only (see docs/SECURITY.md).');
        }

        $container->setParameter('nowo.hot_reload.enabled', $config['enabled']);
        $container->setParameter('nowo.hot_reload.auto_inject', $config['auto_inject']);
        $container->setParameter('nowo.hot_reload.require_frankenphp_env', $config['require_frankenphp_env']);
        $container->setParameter('nowo.hot_reload.allow_production', $config['allow_production']);
        $container->setParameter('nowo.hot_reload.mercure_url', $config['mercure_url']);
        $container->setParameter('nowo.hot_reload.idiomorph', $config['idiomorph']);
        $container->setParameter('nowo.hot_reload.idiomorph_script_url', $config['idiomorph_script_url']);
        $container->setParameter('nowo.hot_reload.hot_reload_script_url', $config['hot_reload_script_url']);
        $container->setParameter('nowo.hot_reload.preserve_selectors', $config['preserve_selectors']);
        $container->setParameter('nowo.hot_reload.preserve_observe', $config['preserve_observe']);
        $container->setParameter('nowo.hot_reload.csp_nonce_request_attribute', $config['csp_nonce_request_attribute']);
        $container->setParameter('nowo.hot_reload.csp_augment_script_src', $config['csp_augment_script_src']);
        $container->setParameter('nowo.hot_reload.csp_script_src_hosts', $config['csp_script_src_hosts']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if (class_exists(AbstractExtension::class)) {
            $loader->load('twig.yaml');
        }

        if (interface_exists(DataCollectorInterface::class) && class_exists(AbstractExtension::class)) {
            $loader->load('profiler.yaml');
        }

        if (class_exists(Command::class)) {
            $loader->load('commands.yaml');
        }
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
