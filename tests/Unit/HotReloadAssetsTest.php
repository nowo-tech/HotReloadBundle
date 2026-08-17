<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit;

use Nowo\HotReloadBundle\HotReloadAssets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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
        self::assertStringContainsString('idiomorph@0.7.4', $html);
        self::assertStringContainsString('frankenphp-hot-reload@1.0.1/+esm', $html);
        self::assertStringContainsString('type="module"', $html);
        self::assertStringContainsString('[id^=\\"sfwdt\\"]', $html);
        self::assertStringContainsString('MutationObserver', $html);
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
        self::assertStringContainsString('frankenphp-hot-reload@1.0.1/+esm', $html);
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

    #[Test]
    public function itAppliesCspNonceFromRequestAttribute(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_csp_nonce', 'abc123');
        $stack = new RequestStack([$request]);

        $assets = $this->createAssets(
            mercureUrl: 'https://hub.test',
            cspNonceRequestAttribute: '_csp_nonce',
            requestStack: $stack,
        );
        $html = $assets->renderHtml();

        self::assertStringContainsString('nonce="abc123"', $html);
        self::assertStringContainsString('data-nowo-hot-reload-preserve-boot', $html);
    }

    #[Test]
    public function itPrefersExplicitCspNonceArgument(): void
    {
        $assets = $this->createAssets(mercureUrl: 'https://hub.test');
        $html   = $assets->renderHtml('explicit-nonce');

        self::assertStringContainsString('nonce="explicit-nonce"', $html);
    }

    #[Test]
    public function itCanDisablePreserveObserve(): void
    {
        $assets = $this->createAssets(mercureUrl: 'https://hub.test', preserveObserve: false);
        $html   = $assets->renderHtml();

        self::assertStringContainsString('var observe = false;', $html);
        self::assertStringNotContainsString('var observe = true;', $html);
    }

    #[Test]
    public function itReturnsCspScriptSrcHostsHintFromScriptUrls(): void
    {
        $assets = $this->createAssets(mercureUrl: 'https://hub.test');

        self::assertSame(['https://cdn.jsdelivr.net'], $assets->getCspScriptSrcHostsHint());
    }

    #[Test]
    public function itReturnsNullNonceWhenRequestStackHasNoRequest(): void
    {
        $assets = $this->createAssets(
            mercureUrl: 'https://hub.test',
            cspNonceRequestAttribute: '_csp_nonce',
            requestStack: new RequestStack(),
        );
        $html = $assets->renderHtml();

        self::assertStringNotContainsString('nonce="', $html);
    }

    #[Test]
    public function itIgnoresNonStringNonceAttributeValues(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_csp_nonce', 123);
        $stack  = new RequestStack([$request]);
        $assets = $this->createAssets(
            mercureUrl: 'https://hub.test',
            cspNonceRequestAttribute: '_csp_nonce',
            requestStack: $stack,
        );

        self::assertStringNotContainsString('nonce="', $assets->renderHtml());
    }

    #[Test]
    public function itBuildsOriginsWithPortsAndSkipsInvalidUrls(): void
    {
        $assets = new HotReloadAssets(
            enabled: true,
            requireFrankenphpEnv: false,
            mercureUrl: null,
            idiomorph: true,
            idiomorphScriptUrl: 'https://cdn.example:8443/idiomorph.js',
            hotReloadScriptUrl: 'ftp://bad.example/module.js',
            preserveSelectors: [],
        );

        self::assertSame(['https://cdn.example:8443'], $assets->getCspScriptSrcHostsHint());

        $empty = new HotReloadAssets(
            enabled: true,
            requireFrankenphpEnv: false,
            mercureUrl: null,
            idiomorph: true,
            idiomorphScriptUrl: '   ',
            hotReloadScriptUrl: 'http://:',
            preserveSelectors: [],
        );
        self::assertSame([], $empty->getCspScriptSrcHostsHint());
    }

    /**
     * @param list<string> $preserveSelectors
     */
    private function createAssets(
        bool $enabled = true,
        bool $requireFrankenphpEnv = true,
        ?string $mercureUrl = null,
        bool $idiomorph = true,
        array $preserveSelectors = ['[id^="sfwdt"]', '.sf-toolbar', '.sf-minitoolbar'],
        bool $preserveObserve = true,
        ?string $cspNonceRequestAttribute = null,
        ?RequestStack $requestStack = null,
    ): HotReloadAssets {
        return new HotReloadAssets(
            enabled: $enabled,
            requireFrankenphpEnv: $requireFrankenphpEnv,
            mercureUrl: $mercureUrl,
            idiomorph: $idiomorph,
            idiomorphScriptUrl: 'https://cdn.jsdelivr.net/npm/idiomorph@0.7.4',
            hotReloadScriptUrl: 'https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm',
            preserveSelectors: $preserveSelectors,
            preserveObserve: $preserveObserve,
            cspNonceRequestAttribute: $cspNonceRequestAttribute,
            requestStack: $requestStack,
        );
    }
}
