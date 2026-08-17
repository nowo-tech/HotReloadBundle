<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit;

use Nowo\HotReloadBundle\HotReloadAssets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HotReloadAssetsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['FRANKENPHP_HOT_RELOAD']);
        parent::tearDown();
    }

    #[Test]
    public function itDoesNotRenderWhenDisabled(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $assets = $this->createAssets(enabled: false);

        self::assertFalse($assets->shouldRender());
        self::assertSame('', $assets->renderHtml());
    }

    #[Test]
    public function itRequiresFrankenphpEnvByDefault(): void
    {
        $assets = $this->createAssets();

        self::assertFalse($assets->shouldRender());
        self::assertSame('', $assets->renderHtml());
    }

    #[Test]
    public function itUsesServerEnvWhenPresent(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test/.well-known/mercure';

        $assets = $this->createAssets();
        $html   = $assets->renderHtml();

        self::assertTrue($assets->shouldRender());
        self::assertStringContainsString('name="frankenphp-hot-reload:url"', $html);
        self::assertStringContainsString('content="https://hub.test/.well-known/mercure"', $html);
        self::assertStringContainsString('data-nowo-hot-reload', $html);
        self::assertStringContainsString('idiomorph', $html);
        self::assertStringContainsString('frankenphp-hot-reload/+esm', $html);
        self::assertStringContainsString('type="module"', $html);
        self::assertStringContainsString('#sfwdt', $html);
    }

    #[Test]
    public function itPrefersConfiguredMercureUrl(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://from-server.test';

        $assets = $this->createAssets(mercureUrl: 'https://from-config.test');

        self::assertSame('https://from-config.test', $assets->resolveMercureUrl());
        self::assertStringContainsString('content="https://from-config.test"', $assets->renderHtml());
    }

    #[Test]
    public function itCanSkipIdiomorphAndPreserveScript(): void
    {
        $assets = $this->createAssets(
            mercureUrl: 'https://hub.test',
            idiomorph: false,
            preserveSelectors: [],
        );
        $html = $assets->renderHtml();

        self::assertStringNotContainsString('idiomorph', $html);
        self::assertStringNotContainsString('data-nowo-hot-reload-preserve-boot', $html);
        self::assertStringContainsString('frankenphp-hot-reload/+esm', $html);
    }

    #[Test]
    public function itCanRenderWithoutEnvWhenNotRequired(): void
    {
        $assets = $this->createAssets(requireFrankenphpEnv: false);
        $html   = $assets->renderHtml();

        self::assertTrue($assets->shouldRender());
        self::assertStringContainsString('name="frankenphp-hot-reload:url"', $html);
        self::assertStringContainsString('content=""', $html);
    }

    /**
     * @param list<string> $preserveSelectors
     */
    private function createAssets(
        bool $enabled = true,
        bool $requireFrankenphpEnv = true,
        ?string $mercureUrl = null,
        bool $idiomorph = true,
        array $preserveSelectors = ['#sfwdt', '.sf-toolbar'],
    ): HotReloadAssets {
        return new HotReloadAssets(
            enabled: $enabled,
            requireFrankenphpEnv: $requireFrankenphpEnv,
            mercureUrl: $mercureUrl,
            idiomorph: $idiomorph,
            idiomorphScriptUrl: 'https://cdn.jsdelivr.net/npm/idiomorph',
            hotReloadScriptUrl: 'https://cdn.jsdelivr.net/npm/frankenphp-hot-reload/+esm',
            preserveSelectors: $preserveSelectors,
        );
    }
}
