<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\DependencyInjection;

use Nowo\HotReloadBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

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
        self::assertNull($config['mercure_url']);
        self::assertTrue($config['idiomorph']);
        self::assertSame('https://cdn.jsdelivr.net/npm/idiomorph', $config['idiomorph_script_url']);
        self::assertSame('https://cdn.jsdelivr.net/npm/frankenphp-hot-reload/+esm', $config['hot_reload_script_url']);
        self::assertSame(['#sfwdt', '.sf-toolbar'], $config['preserve_selectors']);
    }

    #[Test]
    public function itAcceptsCustomValues(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[
            'enabled'                => false,
            'auto_inject'            => false,
            'require_frankenphp_env' => false,
            'mercure_url'            => 'https://example.test/.well-known/mercure',
            'idiomorph'              => false,
            'preserve_selectors'     => ['#custom'],
        ]]);

        self::assertFalse($config['enabled']);
        self::assertFalse($config['auto_inject']);
        self::assertFalse($config['require_frankenphp_env']);
        self::assertSame('https://example.test/.well-known/mercure', $config['mercure_url']);
        self::assertFalse($config['idiomorph']);
        self::assertSame(['#custom'], $config['preserve_selectors']);
    }
}
