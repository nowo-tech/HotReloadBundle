<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function htmlspecialchars;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function sprintf;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Builds the HTML snippet for FrankenPHP Hot Reload (meta + scripts).
 *
 * @see https://frankenphp.dev/docs/hot-reload/
 */
final class HotReloadAssets
{
    public const MARKER = 'data-nowo-hot-reload';

    /**
     * @param list<string> $preserveSelectors
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly bool $requireFrankenphpEnv,
        private readonly ?string $mercureUrl,
        private readonly bool $idiomorph,
        private readonly string $idiomorphScriptUrl,
        private readonly string $hotReloadScriptUrl,
        private readonly array $preserveSelectors,
        private readonly bool $preserveObserve = true,
        private readonly ?string $cspNonceRequestAttribute = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * Resolves the Mercure hub URL from config or FRANKENPHP_HOT_RELOAD.
     */
    public function resolveMercureUrl(): ?string
    {
        if (is_string($this->mercureUrl) && $this->mercureUrl !== '') {
            return $this->mercureUrl;
        }

        $fromServer = $_SERVER['FRANKENPHP_HOT_RELOAD'] ?? null;

        return is_string($fromServer) && $fromServer !== '' ? $fromServer : null;
    }

    /**
     * Whether assets should be rendered for the current request/process.
     */
    public function shouldRender(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $url = $this->resolveMercureUrl();

        if ($url !== null) {
            return true;
        }

        return !$this->requireFrankenphpEnv;
    }

    /**
     * HTML fragment (meta + optional Idiomorph + frankenphp-hot-reload module + preserve helper).
     *
     * @param string|null $cspNonce Explicit CSP nonce; when null, reads {@see $cspNonceRequestAttribute} from the current request
     */
    public function renderHtml(?string $cspNonce = null): string
    {
        if (!$this->shouldRender()) {
            return '';
        }

        $url        = $this->resolveMercureUrl() ?? '';
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $marker     = self::MARKER;
        $nonce      = $this->resolveCspNonce($cspNonce);

        $parts = [
            sprintf('<meta name="frankenphp-hot-reload:url" content="%s" %s>', $escapedUrl, $marker),
        ];

        if ($this->idiomorph) {
            $idiomorphUrl = htmlspecialchars($this->idiomorphScriptUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $parts[]      = sprintf('<script src="%s" %s></script>', $idiomorphUrl, $marker);
        }

        $hotReloadUrl = htmlspecialchars($this->hotReloadScriptUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $parts[]      = sprintf('<script src="%s" type="module" %s></script>', $hotReloadUrl, $marker);

        if ($this->preserveSelectors !== []) {
            $parts[] = $this->buildPreserveScript($nonce);
        }

        return implode("\n", $parts) . "\n";
    }

    /**
     * @return list<string>
     */
    public function getCspScriptSrcHostsHint(): array
    {
        $hosts = [];
        foreach ([$this->idiomorphScriptUrl, $this->hotReloadScriptUrl] as $scriptUrl) {
            $origin = $this->originOf($scriptUrl);
            if ($origin !== null && !in_array($origin, $hosts, true)) {
                $hosts[] = $origin;
            }
        }

        return $hosts;
    }

    private function resolveCspNonce(?string $explicit): ?string
    {
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        if ($this->cspNonceRequestAttribute === null || $this->cspNonceRequestAttribute === '') {
            return null;
        }

        $request = $this->requestStack?->getCurrentRequest();
        if (!$request instanceof Request) {
            return null;
        }

        $value = $request->attributes->get($this->cspNonceRequestAttribute);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function buildPreserveScript(?string $cspNonce): string
    {
        $selectors = json_encode(
            $this->preserveSelectors,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $marker    = self::MARKER;
        $observe   = $this->preserveObserve ? 'true' : 'false';
        $nonceAttr = '';
        if (is_string($cspNonce) && $cspNonce !== '') {
            $nonceAttr = ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return <<<HTML
<script{$nonceAttr} {$marker} data-nowo-hot-reload-preserve-boot>
(function () {
  var selectors = {$selectors};
  var observe = {$observe};
  function mark() {
    selectors.forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (el) {
        el.setAttribute('data-frankenphp-hot-reload-preserve', '');
      });
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mark);
  } else {
    mark();
  }
  if (observe && typeof MutationObserver !== 'undefined') {
    new MutationObserver(mark).observe(document.documentElement, { childList: true, subtree: true });
  }
})();
</script>
HTML;
    }

    private function originOf(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }
        $parts = parse_url($trimmed);
        if (!is_array($parts)) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host   = $parts['host'] ?? null;
        if (!in_array($scheme, ['http', 'https'], true) || !is_string($host) || $host === '') {
            return null;
        }
        $origin = $scheme . '://' . $host;
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}
