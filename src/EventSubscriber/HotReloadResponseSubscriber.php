<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\EventSubscriber;

use Nowo\HotReloadBundle\HotReloadAssets;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Injects FrankenPHP Hot Reload client assets into HTML responses.
 */
final class HotReloadResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly HotReloadAssets $assets,
        private readonly bool $autoInject,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -4096],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->autoInject || !$event->isMainRequest() || !$this->assets->shouldRender()) {
            return;
        }

        $response = $event->getResponse();
        $content  = $response->getContent();

        if ($content === false || $content === '') {
            return;
        }

        if (!$this->isHtmlResponse($response->headers->get('Content-Type', ''))) {
            return;
        }

        if ($this->hasInjectedAssets($content)) {
            return;
        }

        $snippet = $this->assets->renderHtml();

        if (preg_match('/<\/head>/i', $content) === 1) {
            $content = preg_replace('/<\/head>/i', $snippet . '</head>', $content, 1) ?? $content;
        } elseif (preg_match('/<\/body>/i', $content) === 1) {
            $content = preg_replace('/<\/body>/i', $snippet . '</body>', $content, 1) ?? $content;
        } else {
            $content .= $snippet;
        }

        $response->setContent($content);
    }

    private function isHtmlResponse(string $contentType): bool
    {
        return str_contains(strtolower($contentType), 'text/html')
            || str_contains(strtolower($contentType), 'application/xhtml+xml');
    }

    private function hasInjectedAssets(string $content): bool
    {
        return preg_match(
            '/<(?:meta|script)\b[^>]*\b' . preg_quote(HotReloadAssets::MARKER, '/') . '\b/i',
            $content,
        ) === 1;
    }
}
