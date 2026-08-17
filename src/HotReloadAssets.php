<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle;

use function htmlspecialchars;
use function implode;
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
     */
    public function renderHtml(): string
    {
        if (!$this->shouldRender()) {
            return '';
        }

        $url        = $this->resolveMercureUrl() ?? '';
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $marker     = self::MARKER;

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
            $parts[] = $this->buildPreserveScript();
        }

        return implode("\n", $parts) . "\n";
    }

    private function buildPreserveScript(): string
    {
        $selectors = json_encode(
            $this->preserveSelectors,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $marker = self::MARKER;

        return <<<HTML
<script {$marker} data-nowo-hot-reload-preserve-boot>
(function () {
  var selectors = {$selectors};
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
})();
</script>
HTML;
    }
}
