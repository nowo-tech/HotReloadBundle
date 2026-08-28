<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\EventSubscriber;

use Nowo\HotReloadBundle\DependencyInjection\Configuration;
use Nowo\HotReloadBundle\EventSubscriber\HotReloadAssetSubscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class HotReloadAssetSubscriberTest extends TestCase
{
    #[Test]
    public function itServesClientJavascript(): void
    {
        $subscriber = new HotReloadAssetSubscriber();
        $kernel     = $this->createMock(HttpKernelInterface::class);
        $request    = Request::create(Configuration::ASSET_PATH_CLIENT);
        $event      = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        $response = $event->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/javascript', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('nowo-hot-reload-config', (string) $response->getContent());
        self::assertTrue($request->attributes->getBoolean(HotReloadAssetSubscriber::REQUEST_ATTR_SERVED));
    }

    #[Test]
    public function itServesSharedWorkerJavascript(): void
    {
        $subscriber = new HotReloadAssetSubscriber();
        $kernel     = $this->createMock(HttpKernelInterface::class);
        $request    = Request::create(Configuration::ASSET_PATH_SHARED_WORKER);
        $event      = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertStringContainsString('EventSource', (string) $event->getResponse()->getContent());
    }

    #[Test]
    public function itIgnoresOtherPaths(): void
    {
        $subscriber = new HotReloadAssetSubscriber();
        $kernel     = $this->createMock(HttpKernelInterface::class);
        $request    = Request::create('/admin');
        $event      = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }
}
