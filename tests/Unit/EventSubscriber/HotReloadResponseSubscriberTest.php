<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\EventSubscriber;

use Nowo\HotReloadBundle\EventSubscriber\HotReloadResponseSubscriber;
use Nowo\HotReloadBundle\HotReloadAssets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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

    private function createSubscriber(bool $autoInject = true): HotReloadResponseSubscriber
    {
        $assets = new HotReloadAssets(
            enabled: true,
            requireFrankenphpEnv: true,
            mercureUrl: null,
            idiomorph: true,
            idiomorphScriptUrl: 'https://cdn.jsdelivr.net/npm/idiomorph',
            hotReloadScriptUrl: 'https://cdn.jsdelivr.net/npm/frankenphp-hot-reload/+esm',
            preserveSelectors: [],
        );

        return new HotReloadResponseSubscriber($assets, $autoInject);
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
