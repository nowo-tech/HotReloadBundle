<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\EventSubscriber;

use Nowo\HotReloadBundle\Event\HotReloadInjectEvent;
use Nowo\HotReloadBundle\HotReloadAssets;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Injects FrankenPHP Hot Reload client assets into HTML responses.
 */
final class HotReloadResponseSubscriber implements EventSubscriberInterface
{
    public const REQUEST_ATTR_INJECTED = '_nowo_hot_reload_injected';

    /**
     * @param list<string> $cspScriptSrcHosts
     */
    public function __construct(
        private readonly HotReloadAssets $assets,
        private readonly bool $autoInject,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly bool $cspAugmentScriptSrc = true,
        private readonly array $cspScriptSrcHosts = [],
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

        if (!$this->isHtmlResponse($response, $content)) {
            return;
        }

        if ($this->hasInjectedAssets($content)) {
            $event->getRequest()->attributes->set(self::REQUEST_ATTR_INJECTED, true);

            return;
        }

        $snippet = $this->assets->renderHtml();

        if ($this->eventDispatcher instanceof EventDispatcherInterface) {
            $injectEvent = new HotReloadInjectEvent($event->getRequest(), $response, $snippet);
            $this->eventDispatcher->dispatch($injectEvent);
            $snippet = $injectEvent->getSnippet();
        }

        if (preg_match('/<\/head>/i', $content) === 1) {
            $content = preg_replace('/<\/head>/i', $snippet . '</head>', $content, 1) ?? $content;
        } elseif (preg_match('/<\/body>/i', $content) === 1) {
            $content = preg_replace('/<\/body>/i', $snippet . '</body>', $content, 1) ?? $content;
        } else {
            $content .= $snippet;
        }

        $response->setContent($content);
        $event->getRequest()->attributes->set(self::REQUEST_ATTR_INJECTED, true);
        $this->augmentContentSecurityPolicy($response);
    }

    private function isHtmlResponse(Response $response, string $content): bool
    {
        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if (str_contains($contentType, 'text/html')
            || str_contains($contentType, 'application/xhtml+xml')
        ) {
            return true;
        }

        // Explicit non-HTML Content-Type → skip. Empty/missing → sniff body (Symfony often omits CT).
        if ($contentType !== '') {
            return false;
        }

        return str_contains(substr($content, 0, 512), '<html');
    }

    private function hasInjectedAssets(string $content): bool
    {
        return preg_match(
            '/<(?:meta|script)\b[^>]*\b' . preg_quote(HotReloadAssets::MARKER, '/') . '\b/i',
            $content,
        ) === 1;
    }

    private function augmentContentSecurityPolicy(Response $response): void
    {
        if (!$this->cspAugmentScriptSrc) {
            return;
        }

        $hosts = $this->cspScriptSrcHosts;
        if ($hosts === []) {
            $hosts = $this->assets->getCspScriptSrcHostsHint();
        }
        if ($hosts === []) {
            return;
        }

        $csp = $response->headers->get('Content-Security-Policy');
        if ($csp === null || $csp === '') {
            return;
        }

        $updated = $this->mergeScriptSrcHosts($csp, $hosts);
        if ($updated !== $csp) {
            $response->headers->set('Content-Security-Policy', $updated);
        }
    }

    /**
     * @param list<string> $hosts
     */
    private function mergeScriptSrcHosts(string $csp, array $hosts): string
    {
        if (preg_match('/script-src([^;]*)/i', $csp, $matches) === 1) {
            $directive = $matches[1];
            $missing   = [];
            foreach ($hosts as $host) {
                if (!str_contains($directive, $host)) {
                    $missing[] = $host;
                }
            }
            if ($missing === []) {
                return $csp;
            }

            $replacement = 'script-src' . rtrim($directive) . ' ' . implode(' ', $missing);

            return preg_replace('/script-src[^;]*/i', $replacement, $csp, 1) ?? $csp;
        }

        return rtrim($csp, '; ') . '; script-src ' . implode(' ', $hosts);
    }
}
