<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\DependencyInjection;

use Nowo\HotReloadBundle\DependencyInjection\Configuration;
use Nowo\HotReloadBundle\DependencyInjection\HotReloadExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

final class ConfigurationTest extends TestCase
{
    #[Test]
    public function itProvidesDefaults(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[]]);

        self::assertTrue($config['enabled']);
        self::assertTrue($config['auto_inject']);
        self::assertTrue($config['require_frankenphp_env']);
        self::assertFalse($config['allow_production']);
        self::assertNull($config['mercure_url']);
        self::assertTrue($config['idiomorph']);
        self::assertSame(Configuration::DEFAULT_IDIOMORPH_SCRIPT_URL, $config['idiomorph_script_url']);
        self::assertSame(Configuration::DEFAULT_HOT_RELOAD_SCRIPT_URL, $config['hot_reload_script_url']);
        self::assertSame(Configuration::DEFAULT_PRESERVE_SELECTORS, $config['preserve_selectors']);
        self::assertTrue($config['preserve_observe']);
        self::assertNull($config['csp_nonce_request_attribute']);
        self::assertTrue($config['csp_augment_script_src']);
        self::assertSame(Configuration::DEFAULT_CSP_SCRIPT_SRC_HOSTS, $config['csp_script_src_hosts']);
    }

    #[Test]
    public function itAcceptsCustomValues(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[
            'enabled'                     => false,
            'auto_inject'                 => false,
            'require_frankenphp_env'      => false,
            'allow_production'            => true,
            'mercure_url'                 => 'https://example.test/.well-known/mercure',
            'idiomorph'                   => false,
            'preserve_selectors'          => ['#custom'],
            'preserve_observe'            => false,
            'csp_nonce_request_attribute' => '_csp_nonce',
            'csp_augment_script_src'      => false,
            'csp_script_src_hosts'        => ['https://assets.example'],
        ]]);

        self::assertFalse($config['enabled']);
        self::assertFalse($config['auto_inject']);
        self::assertFalse($config['require_frankenphp_env']);
        self::assertTrue($config['allow_production']);
        self::assertSame('https://example.test/.well-known/mercure', $config['mercure_url']);
        self::assertFalse($config['idiomorph']);
        self::assertSame(['#custom'], $config['preserve_selectors']);
        self::assertFalse($config['preserve_observe']);
        self::assertSame('_csp_nonce', $config['csp_nonce_request_attribute']);
        self::assertFalse($config['csp_augment_script_src']);
        self::assertSame(['https://assets.example'], $config['csp_script_src_hosts']);
    }

    #[Test]
    public function itRejectsEnabledInProdUnlessAllowProduction(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.debug', false);

        $extension = new HotReloadExtension();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('allow_production');

        $extension->load([['enabled' => true]], $container);
    }

    #[Test]
    public function itAllowsEnabledInProdWhenAllowProductionTrue(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.debug', false);

        $extension = new HotReloadExtension();
        $extension->load([[
            'enabled'          => true,
            'allow_production' => true,
        ]], $container);

        self::assertTrue($container->getParameter('nowo.hot_reload.enabled'));
        self::assertTrue($container->getParameter('nowo.hot_reload.allow_production'));
    }
}
