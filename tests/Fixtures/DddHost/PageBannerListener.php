<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Fixtures\DddHost;

use Nowo\HotReloadBundle\Event\HotReloadInjectEvent;

use function str_replace;
use function str_starts_with;

/**
 * Host listener on the framework-free accessors only (no HttpFoundation import): marks the body with the path and
 * cancels the injection under /no-reload.
 */
final class PageBannerListener
{
    public function __invoke(HotReloadInjectEvent $event): void
    {
        if (str_starts_with($event->getPath(), '/no-reload')) {
            $event->setSnippet('');

            return;
        }

        $event->setBody(str_replace('<body>', '<body data-banner="' . $event->getPath() . '|' . $event->getContentType() . '">', $event->getBody()));
    }
}
