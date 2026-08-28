<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\EventSubscriber;

use Nowo\HotReloadBundle\DependencyInjection\Configuration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function dirname;
use function file_get_contents;
use function is_file;
use function is_readable;
use function str_ends_with;

/**
 * Serves bundle hot-reload client / SharedWorker scripts from Resources/public.
 */
final class HotReloadAssetSubscriber implements EventSubscriberInterface
{
    public const REQUEST_ATTR_SERVED = '_nowo_hot_reload_asset_served';

    public static function getSubscribedEvents(): array
    {
        // Before RouterListener (32) so these paths are not 404'd.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 34],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        $map  = [
            Configuration::ASSET_PATH_CLIENT        => 'hot-reload-client.js',
            Configuration::ASSET_PATH_SHARED_WORKER => 'hot-reload-shared-worker.js',
        ];

        if (!isset($map[$path])) {
            return;
        }

        $file = dirname(__DIR__) . '/Resources/public/' . $map[$path];
        if (!is_file($file) || !is_readable($file) || !str_ends_with($file, '.js')) {
            return;
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return;
        }

        $response = new Response($contents, 200, [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);

        $event->getRequest()->attributes->set(self::REQUEST_ATTR_SERVED, true);
        $event->setResponse($response);
    }
}
