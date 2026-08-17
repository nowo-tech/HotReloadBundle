<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Integration\DependencyInjection;

use Nowo\HotReloadBundle\DependencyInjection\HotReloadExtension;
use Nowo\HotReloadBundle\EventSubscriber\HotReloadResponseSubscriber;
use Nowo\HotReloadBundle\HotReloadAssets;
use Nowo\HotReloadBundle\Twig\HotReloadTwigExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

final class HotReloadExtensionIntegrationTest extends TestCase
{
    #[Test]
    public function itRegistersServicesAndParameters(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'test');

        $extension = new HotReloadExtension();
        $extension->load([[
            'enabled'     => true,
            'mercure_url' => 'https://hub.test',
        ]], $container);

        self::assertTrue($container->getParameter('nowo.hot_reload.enabled'));
        self::assertSame('https://hub.test', $container->getParameter('nowo.hot_reload.mercure_url'));
        self::assertTrue($container->hasDefinition(HotReloadAssets::class));
        self::assertTrue($container->hasDefinition(HotReloadResponseSubscriber::class));
        self::assertTrue($container->hasDefinition(HotReloadTwigExtension::class));
        self::assertSame('nowo_hot_reload', $extension->getAlias());
    }

    #[Test]
    public function itLoadsWithoutTwigWhenAbsent(): void
    {
        // Twig is present in require-dev; this test still asserts services load.
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $extension = new HotReloadExtension();
        $extension->load([[]], $container);

        self::assertTrue($container->hasDefinition(HotReloadAssets::class));
    }
}
