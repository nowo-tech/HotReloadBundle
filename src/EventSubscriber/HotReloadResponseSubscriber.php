<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\EventSubscriber;

use Nowo\HotReloadBundle\Event\HotReloadInjectEvent;
use Nowo\HotReloadBundle\HotReloadAssets;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function in_array;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Injects FrankenPHP Hot Reload client assets into HTML responses.
 */
final class HotReloadResponseSubscriber implements EventSubscriberInterface
{
    public const REQUEST_ATTR_INJECTED = '_nowo_hot_reload_injected';

    private const CSP_HEADERS = ['Content-Security-Policy', 'Content-Security-Policy-Report-Only'];

    /**
     * @param list<string> $cspScriptSrcHosts
     * @param list<string> $ignorePathPrefixes
     */
    public function __construct(
        private readonly HotReloadAssets $assets,
        private readonly bool $autoInject,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly bool $cspAugmentScriptSrc = true,
        private readonly array $cspScriptSrcHosts = [],
        private readonly array $ignorePathPrefixes = [],
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

        $request = $event->getRequest();
        if ($this->shouldSkipRequest($request)) {
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
            $injectEvent = new HotReloadInjectEvent($request, $response, $snippet);
            $this->eventDispatcher->dispatch($injectEvent);
            $snippet = $injectEvent->getSnippet();
        }

        // Empty snippet = host listener cancelled injection (e.g. HotReloadInjectEvent::setSnippet('')).
        if ($snippet === '') {
            return;
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

    /**
     * Skip Symfony Web Debug Toolbar / profiler HTML fragments.
     *
     * WDT evals every &lt;script&gt; in the fragment; injecting the hot-reload JSON
     * config script causes SyntaxError: Unexpected token ':'.
     *
     * Set ignore_path_prefixes to [] to disable this skip.
     */
    private function shouldSkipRequest(Request $request): bool
    {
        $path = $request->getPathInfo();
        foreach ($this->ignorePathPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
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

        foreach (self::CSP_HEADERS as $headerName) {
            $policies = $response->headers->all($headerName);
            if ($policies === []) {
                continue;
            }

            $changed = false;
            $updated = [];
            foreach ($policies as $policy) {
                $policy    = (string) $policy;
                $merged    = $policy === '' ? $policy : $this->mergeScriptSrcHosts($policy, array_values($hosts));
                $changed   = $changed || $merged !== $policy;
                $updated[] = $merged;
            }

            if ($changed) {
                $response->headers->set($headerName, $updated);
            }
        }
    }

    /**
     * Adds the asset hosts to every script-src / script-src-elem directive of a policy.
     *
     * When the policy has no script-src but restricts scripts through default-src, a new
     * script-src is seeded with the default-src sources so same-origin scripts (including the
     * bundle client) stay allowed. Policies that do not restrict scripts at all are left untouched.
     *
     * @param list<string> $hosts
     */
    private function mergeScriptSrcHosts(string $csp, array $hosts): string
    {
        /** @var list<array{name: string, sources: list<string>}> $directives */
        $directives = [];
        foreach (explode(';', $csp) as $rawDirective) {
            $tokens = preg_split('/\s+/', trim($rawDirective), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($tokens === []) {
                continue;
            }
            $name         = strtolower(array_shift($tokens));
            $directives[] = ['name' => $name, 'sources' => $tokens];
        }

        $changed       = false;
        $hasScriptSrc  = false;
        $defaultSource = null;
        foreach ($directives as $index => $directive) {
            if ($directive['name'] === 'default-src' && $defaultSource === null) {
                $defaultSource = $directive['sources'];
            }
            if ($directive['name'] !== 'script-src' && $directive['name'] !== 'script-src-elem') {
                continue;
            }
            $hasScriptSrc = $hasScriptSrc || $directive['name'] === 'script-src';
            $sources      = $this->addMissingSources($directive['sources'], $hosts);
            if ($sources !== $directive['sources']) {
                $directives[$index]['sources'] = $sources;
                $changed                       = true;
            }
        }

        if (!$hasScriptSrc && $defaultSource !== null) {
            $directives[] = ['name' => 'script-src', 'sources' => $this->addMissingSources($defaultSource, $hosts)];
            $changed      = true;
        }

        if (!$changed) {
            return $csp;
        }

        return implode('; ', array_map(
            static fn (array $directive): string => implode(' ', [$directive['name'], ...$directive['sources']]),
            $directives,
        ));
    }

    /**
     * @param list<string> $sources
     * @param list<string> $hosts
     *
     * @return list<string>
     */
    private function addMissingSources(array $sources, array $hosts): array
    {
        $known   = array_map('strtolower', $sources);
        $missing = [];
        foreach ($hosts as $host) {
            if (!in_array(strtolower($host), $known, true)) {
                $known[]   = strtolower($host);
                $missing[] = $host;
            }
        }
        if ($missing === []) {
            return $sources;
        }

        // 'none' cannot be combined with other source expressions.
        $sources = array_values(array_filter($sources, static fn (string $source): bool => strtolower($source) !== "'none'"));

        return [...$sources, ...$missing];
    }
}
