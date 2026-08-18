<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\DataCollector;

use Nowo\HotReloadBundle\DataCollector\HotReloadDataCollector;
use Nowo\HotReloadBundle\Diagnostics\HotReloadCheck;
use Nowo\HotReloadBundle\Diagnostics\HotReloadDiagnostics;
use Nowo\HotReloadBundle\EventSubscriber\HotReloadResponseSubscriber;
use Nowo\HotReloadBundle\HotReloadAssets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function str_repeat;
use function strlen;

final class HotReloadDataCollectorTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['FRANKENPHP_HOT_RELOAD']);
        parent::tearDown();
    }

    #[Test]
    public function itCollectsConfigAndMarksInjectedOnLateCollect(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test/.well-known/mercure';

        $collector = $this->createCollector();
        $request   = Request::create('/');
        $request->server->set('FRANKENPHP_HOT_RELOAD', 'https://hub.test/.well-known/mercure');
        $response = new Response('<html></html>', 200, ['Content-Type' => 'text/html']);

        $collector->collect($request, $response);
        self::assertSame('ready', $collector->getStatus());
        self::assertFalse($collector->isInjected());
        self::assertTrue($collector->shouldRender());
        self::assertTrue($collector->isEnabled());
        self::assertTrue($collector->isAutoInject());
        self::assertTrue($collector->isRequireFrankenphpEnv());
        self::assertSame('https://hub.test/.well-known/mercure', $collector->getMercureUrl());
        self::assertSame('https://hub.test/.well-known/mercure', $collector->getMercureUrlShort());
        self::assertSame('https://hub.test/.well-known/mercure', $collector->getFrankenphpHotReloadEnv());
        self::assertSame('https://cdn.jsdelivr.net/npm/idiomorph@0.7.4', $collector->getIdiomorphScriptUrl());
        self::assertSame('https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm', $collector->getHotReloadScriptUrl());

        $request->attributes->set(HotReloadResponseSubscriber::REQUEST_ATTR_INJECTED, true);
        $collector->lateCollect();

        self::assertTrue($collector->isInjected());
        self::assertSame('active', $collector->getStatus());
        self::assertSame(HotReloadDataCollector::NAME, $collector->getName());
        self::assertSame('dev', $collector->getEnvironment());
        self::assertNotSame([], $collector->getChecks());
        self::assertSame(HotReloadCheck::STATUS_PASS, $collector->getDiagnosticStatus());
        self::assertStringContainsString('passed', $collector->getDiagnosticSummary());
        self::assertSame([], $collector->getActionableChecks());
    }

    #[Test]
    public function itReturnsNullForEmptyMercureAndEnvValues(): void
    {
        $collector = $this->createCollector();
        $collector->collect(Request::create('/'), new Response(''));

        self::assertNull($collector->getMercureUrl());
        self::assertNull($collector->getMercureUrlShort());
        self::assertNull($collector->getFrankenphpHotReloadEnv());
        self::assertSame('idle', $collector->getStatus());
    }

    #[Test]
    public function itReportsDisabledAndIdleStatuses(): void
    {
        $disabled = $this->createCollector(enabled: false);
        $disabled->collect(Request::create('/'), new Response(''));
        self::assertSame('disabled', $disabled->getStatus());

        $idle = $this->createCollector();
        $idle->collect(Request::create('/'), new Response(''));
        self::assertSame('idle', $idle->getStatus());
        self::assertFalse($idle->shouldRender());
    }

    #[Test]
    public function itShortensLongMercureUrlsForTheToolbar(): void
    {
        $long                             = '/.well-known/mercure?topic=https%3A%2F%2Ffrankenphp.dev%2Fhot-reload%2Fd780fa9ced72db39';
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = $long;

        $collector = $this->createCollector();
        $request   = Request::create('/');
        $request->server->set('FRANKENPHP_HOT_RELOAD', $long);
        $collector->collect($request, new Response(''));

        self::assertSame($long, $collector->getMercureUrl());
        self::assertSame('/.well-known/mercure?...', $collector->getMercureUrlShort());
        self::assertLessThan(strlen($long), strlen($collector->getMercureUrlShort()));
    }

    #[Test]
    public function itShortensLongMercureUrlsWithoutQueryByEllipsis(): void
    {
        $long                             = 'https://example.test/' . str_repeat('mercure-hub-path/', 8);
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = $long;

        $collector = $this->createCollector();
        $request   = Request::create('/');
        $request->server->set('FRANKENPHP_HOT_RELOAD', $long);
        $collector->collect($request, new Response(''));

        $short = $collector->getMercureUrlShort();
        self::assertNotNull($short);
        self::assertSame(HotReloadDataCollector::MERCURE_URL_TOOLBAR_MAX, strlen($short));
        self::assertStringEndsWith('...', $short);
        self::assertStringStartsWith('https://example.test/', $short);
    }

    #[Test]
    public function itSerializesWithoutRequestReference(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $collector = $this->createCollector();
        $request   = Request::create('/');
        $request->attributes->set(HotReloadResponseSubscriber::REQUEST_ATTR_INJECTED, true);
        $collector->collect($request, new Response('<html></html>'));
        $collector->lateCollect();

        $payload = serialize($collector);
        /** @var HotReloadDataCollector $restored */
        $restored = unserialize($payload);

        self::assertTrue($restored->isInjected());
        self::assertSame('active', $restored->getStatus());
        self::assertSame(['[id^="sfwdt"]'], $restored->getPreserveSelectors());
        self::assertTrue($restored->isIdiomorph());
        self::assertTrue($restored->isPreserveObserve());
        self::assertTrue($restored->isCspAugmentScriptSrc());
        self::assertSame(['https://cdn.jsdelivr.net'], $restored->getCspScriptSrcHosts());
        self::assertNull($restored->getCspNonceRequestAttribute());
    }

    #[Test]
    public function itResetsCollectedData(): void
    {
        $collector = $this->createCollector(enabled: false);
        $collector->collect(Request::create('/'), new Response(''));
        $collector->reset();

        self::assertSame('disabled', $collector->getStatus());
        self::assertSame([], $collector->getData());
        self::assertSame([], $collector->getChecks());
        self::assertSame(HotReloadCheck::STATUS_INFO, $collector->getDiagnosticStatus());
        self::assertSame('', $collector->getDiagnosticSummary());
        self::assertSame('', $collector->getEnvironment());
    }

    #[Test]
    public function itIgnoresInvalidStoredChecksAndNonStringDiagnosticStatus(): void
    {
        $collector = $this->createCollector();
        $collector->__unserialize([
            'data' => [
                'checks'             => 'nope',
                'diagnostic_status'  => 12,
                'diagnostic_summary' => null,
            ],
        ]);
        self::assertSame([], $collector->getChecks());
        self::assertSame(HotReloadCheck::STATUS_INFO, $collector->getDiagnosticStatus());
        self::assertSame('', $collector->getDiagnosticSummary());

        $collector->__unserialize([
            'data' => [
                'checks' => [
                    'bad',
                    ['id' => 'x', 'label' => 'X', 'status' => 'fail', 'detail' => 'no', 'fix' => 'do it'],
                ],
            ],
        ]);
        self::assertCount(1, $collector->getChecks());
        self::assertCount(1, $collector->getActionableChecks());
    }

    #[Test]
    public function lateCollectWithoutARequestLeavesInjectionFalse(): void
    {
        $collector = $this->createCollector();
        $collector->lateCollect();
        self::assertFalse($collector->isInjected());
    }

    private function createCollector(bool $enabled = true): HotReloadDataCollector
    {
        $assets = new HotReloadAssets(
            enabled: $enabled,
            requireFrankenphpEnv: true,
            mercureUrl: null,
            idiomorph: true,
            idiomorphScriptUrl: 'https://cdn.jsdelivr.net/npm/idiomorph@0.7.4',
            hotReloadScriptUrl: 'https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm',
            preserveSelectors: ['[id^="sfwdt"]'],
            preserveObserve: true,
        );

        return new HotReloadDataCollector(
            assets: $assets,
            autoInject: true,
            idiomorph: true,
            idiomorphScriptUrl: 'https://cdn.jsdelivr.net/npm/idiomorph@0.7.4',
            hotReloadScriptUrl: 'https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm',
            preserveSelectors: ['[id^="sfwdt"]'],
            preserveObserve: true,
            cspNonceRequestAttribute: null,
            cspAugmentScriptSrc: true,
            cspScriptSrcHosts: ['https://cdn.jsdelivr.net'],
            requireFrankenphpEnv: true,
            enabled: $enabled,
            diagnostics: new HotReloadDiagnostics(
                assets: $assets,
                enabled: $enabled,
                autoInject: true,
                requireFrankenphpEnv: true,
                allowProduction: false,
                mercureUrl: null,
                environment: 'dev',
                idiomorph: true,
                cspNonceRequestAttribute: null,
                cspAugmentScriptSrc: true,
            ),
        );
    }
}
