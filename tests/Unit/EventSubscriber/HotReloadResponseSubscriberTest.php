<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\EventSubscriber;

use Nowo\HotReloadBundle\Event\HotReloadInjectEvent;
use Nowo\HotReloadBundle\EventSubscriber\HotReloadResponseSubscriber;
use Nowo\HotReloadBundle\HotReloadAssets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

final class HotReloadResponseSubscriberTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['FRANKENPHP_HOT_RELOAD']);
        parent::tearDown();
    }

    #[Test]
    public function itSubscribesToKernelResponseLate(): void
    {
        $events = HotReloadResponseSubscriber::getSubscribedEvents();

        self::assertSame(['onKernelResponse', -4096], $events[KernelEvents::RESPONSE]);
    }

    #[Test]
    public function itInjectsBeforeHeadWhenPresent(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('<html><head><title>t</title></head><body>ok</body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
        $event = $this->createEvent($response);

        $this->createSubscriber()->onKernelResponse($event);

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('frankenphp-hot-reload:url', $content);
        self::assertMatchesRegularExpression('/data-nowo-hot-reload[\s\S]*<\/head>/i', $content);
    }

    #[Test]
    public function itInjectsBeforeBodyWhenHeadMissing(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('<html><body>ok</body></html>', 200, ['Content-Type' => 'text/html']);
        $this->createSubscriber()->onKernelResponse($this->createEvent($response));

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertMatchesRegularExpression('/data-nowo-hot-reload[\s\S]*<\/body>/i', $content);
    }

    #[Test]
    public function itAppendsWhenNoBodyOrHead(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('<html>ok</html>', 200, ['Content-Type' => 'text/html']);
        $this->createSubscriber()->onKernelResponse($this->createEvent($response));

        self::assertStringContainsString('frankenphp-hot-reload:url', (string) $response->getContent());
    }

    #[Test]
    public function itSkipsNonHtmlResponses(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('{"a":1}', 200, ['Content-Type' => 'application/json']);
        $this->createSubscriber()->onKernelResponse($this->createEvent($response));

        self::assertSame('{"a":1}', $response->getContent());
    }

    #[Test]
    public function itSniffsHtmlWhenContentTypeMissing(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('<html><head></head><body>ok</body></html>', 200);
        $this->createSubscriber()->onKernelResponse($this->createEvent($response));

        self::assertStringContainsString('frankenphp-hot-reload:url', (string) $response->getContent());
    }

    #[Test]
    public function itSkipsWhenAutoInjectDisabled(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('<html><body>ok</body></html>', 200, ['Content-Type' => 'text/html']);
        $this->createSubscriber(autoInject: false)->onKernelResponse($this->createEvent($response));

        self::assertSame('<html><body>ok</body></html>', $response->getContent());
    }

    #[Test]
    public function itSkipsSubRequests(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('<html><body>ok</body></html>', 200, ['Content-Type' => 'text/html']);
        $this->createSubscriber()->onKernelResponse($this->createEvent($response, main: false));

        self::assertSame('<html><body>ok</body></html>', $response->getContent());
    }

    #[Test]
    public function itSkipsWhenAlreadyInjected(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $html     = '<html><head><meta data-nowo-hot-reload></head><body>ok</body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);
        $this->createSubscriber()->onKernelResponse($this->createEvent($response));

        self::assertSame($html, $response->getContent());
    }

    #[Test]
    public function itSkipsEmptyContent(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('', 200, ['Content-Type' => 'text/html']);
        $this->createSubscriber()->onKernelResponse($this->createEvent($response));

        self::assertSame('', $response->getContent());
    }

    #[Test]
    public function itDispatchesInjectEventAndAllowsSnippetOverride(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(HotReloadInjectEvent::class, static function (HotReloadInjectEvent $event): void {
            self::assertSame('/', $event->getRequest()->getPathInfo());
            self::assertInstanceOf(Response::class, $event->getResponse());
            $event->setSnippet('<!--custom-hot-reload-->');
        });

        $response = new Response('<html><head></head><body>ok</body></html>', 200, ['Content-Type' => 'text/html']);
        $this->createSubscriber(eventDispatcher: $dispatcher)->onKernelResponse($this->createEvent($response));

        self::assertStringContainsString('<!--custom-hot-reload-->', (string) $response->getContent());
        self::assertStringNotContainsString('frankenphp-hot-reload:url', (string) $response->getContent());
    }

    #[Test]
    public function itAugmentsExistingCspScriptSrc(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('<html><head></head><body>ok</body></html>', 200, [
            'Content-Type'            => 'text/html',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'nonce-abc'",
        ]);
        $this->createSubscriber()->onKernelResponse($this->createEvent($response));

        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString('https://cdn.jsdelivr.net', $csp);
        self::assertStringContainsString("script-src 'self' 'nonce-abc' https://cdn.jsdelivr.net", $csp);
    }

    #[Test]
    public function itAppendsScriptSrcWhenCspHasNoScriptSrc(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('<html><head></head><body>ok</body></html>', 200, [
            'Content-Type'            => 'text/html',
            'Content-Security-Policy' => "default-src 'self'",
        ]);
        $this->createSubscriber()->onKernelResponse($this->createEvent($response));

        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'; script-src https://cdn.jsdelivr.net", $csp);
    }

    #[Test]
    public function itSkipsCspAugmentWhenDisabled(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $response = new Response('<html><head></head><body>ok</body></html>', 200, [
            'Content-Type'            => 'text/html',
            'Content-Security-Policy' => "script-src 'self'",
        ]);
        $this->createSubscriber(cspAugmentScriptSrc: false)->onKernelResponse($this->createEvent($response));

        self::assertSame("script-src 'self'", $response->headers->get('Content-Security-Policy'));
    }

    #[Test]
    public function itDerivesCspHostsFromScriptUrlsWhenConfigEmpty(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $assets = new HotReloadAssets(
            enabled: true,
            requireFrankenphpEnv: true,
            mercureUrl: null,
            idiomorph: true,
            idiomorphScriptUrl: 'https://cdn.jsdelivr.net/npm/idiomorph@0.7.4',
            hotReloadScriptUrl: 'https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm',
            preserveSelectors: [],
        );
        $subscriber = new HotReloadResponseSubscriber($assets, true, null, true, []);
        $response   = new Response('<html><head></head><body>ok</body></html>', 200, [
            'Content-Type'            => 'text/html',
            'Content-Security-Policy' => "default-src 'self'",
        ]);
        $subscriber->onKernelResponse($this->createEvent($response));

        self::assertStringContainsString('https://cdn.jsdelivr.net', (string) $response->headers->get('Content-Security-Policy'));
    }

    #[Test]
    public function itSkipsCspAugmentWhenNoHostsCanBeDerived(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $assets = new HotReloadAssets(
            enabled: true,
            requireFrankenphpEnv: true,
            mercureUrl: null,
            idiomorph: false,
            idiomorphScriptUrl: '/local/idiomorph.js',
            hotReloadScriptUrl: '/local/hot-reload.js',
            preserveSelectors: [],
        );
        $subscriber = new HotReloadResponseSubscriber($assets, true, null, true, []);
        $csp        = "script-src 'self'";
        $response   = new Response('<html><head></head><body>ok</body></html>', 200, [
            'Content-Type'            => 'text/html',
            'Content-Security-Policy' => $csp,
        ]);
        $subscriber->onKernelResponse($this->createEvent($response));

        self::assertSame($csp, $response->headers->get('Content-Security-Policy'));
    }

    #[Test]
    public function itLeavesCspUnchangedWhenHostAlreadyPresent(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';

        $csp      = "script-src 'self' https://cdn.jsdelivr.net";
        $response = new Response('<html><head></head><body>ok</body></html>', 200, [
            'Content-Type'            => 'text/html',
            'Content-Security-Policy' => $csp,
        ]);
        $this->createSubscriber()->onKernelResponse($this->createEvent($response));

        self::assertSame($csp, $response->headers->get('Content-Security-Policy'));
    }

    private function createSubscriber(
        bool $autoInject = true,
        ?EventDispatcher $eventDispatcher = null,
        bool $cspAugmentScriptSrc = true,
    ): HotReloadResponseSubscriber {
        $assets = new HotReloadAssets(
            enabled: true,
            requireFrankenphpEnv: true,
            mercureUrl: null,
            idiomorph: true,
            idiomorphScriptUrl: 'https://cdn.jsdelivr.net/npm/idiomorph@0.7.4',
            hotReloadScriptUrl: 'https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm',
            preserveSelectors: [],
        );

        return new HotReloadResponseSubscriber(
            $assets,
            $autoInject,
            $eventDispatcher,
            $cspAugmentScriptSrc,
            ['https://cdn.jsdelivr.net'],
        );
    }

    private function createEvent(Response $response, bool $main = true): ResponseEvent
    {
        $kernel = $this->createMock(KernelInterface::class);

        return new ResponseEvent(
            $kernel,
            Request::create('/'),
            $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
            $response,
        );
    }
}
