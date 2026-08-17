# Configuration

All options live under `nowo_hot_reload`:

| Option | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Master switch. When `false`, nothing is injected even if `FRANKENPHP_HOT_RELOAD` is set. |
| `auto_inject` | `true` | When `true`, `HotReloadResponseSubscriber` injects assets into HTML responses. |
| `require_frankenphp_env` | `true` | When `true`, inject only if `mercure_url` is set or `$_SERVER['FRANKENPHP_HOT_RELOAD']` is present. When `false`, assets may render with an empty Mercure URL. |
| `allow_production` | `false` | When `false`, `enabled: true` in the `prod` environment raises `InvalidConfigurationException`. |
| `mercure_url` | `null` | Optional Mercure hub URL. When `null`, uses `$_SERVER['FRANKENPHP_HOT_RELOAD']` when present. |
| `idiomorph` | `true` | When `true`, include the Idiomorph classic script for DOM morphing. |
| `idiomorph_script_url` | `https://cdn.jsdelivr.net/npm/idiomorph@0.7.4` | URL of the Idiomorph script (version-pinned). |
| `hot_reload_script_url` | `https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm` | URL of the frankenphp-hot-reload ESM module (version-pinned). |
| `preserve_selectors` | `['[id^="sfwdt"]', '.sf-toolbar', '.sf-minitoolbar']` | CSS selectors that receive `data-frankenphp-hot-reload-preserve`. |
| `preserve_observe` | `true` | When `true`, the preserve boot uses `MutationObserver` for late-injected toolbar nodes. |
| `csp_nonce_request_attribute` | `null` | Request attribute holding the CSP nonce for the inline preserve script (e.g. `_csp_nonce`). |
| `csp_augment_script_src` | `true` | When `true`, append hosts to an existing response `Content-Security-Policy` `script-src` after injection. |
| `csp_script_src_hosts` | `['https://cdn.jsdelivr.net']` | Hosts used by CSP augment. Empty list → derive origins from the script URLs. |

## Render gate

Assets are rendered only when **all** of the following hold:

1. `enabled` is `true`
2. Either `mercure_url` / `FRANKENPHP_HOT_RELOAD` resolves to a non-empty URL, **or** `require_frankenphp_env` is `false`

Injected markup is marked with `data-nowo-hot-reload` so the subscriber does not inject twice.

## CSP

See [CSP.md](CSP.md) for nonce + CDN / self-host guidance.

## HTML detection

`HotReloadResponseSubscriber` treats a response as HTML when `Content-Type` contains `text/html` or `application/xhtml+xml`, **or** when `Content-Type` is missing/empty and the body starts with markup containing `<html`.

Performance implications (one HTML rewrite when the gate is open; zero body work when not) are described in [Performance](PERFORMANCE.md).

## Web Debug Toolbar

When Twig is available, `HotReloadDataCollector` registers as profiler id `nowo_hot_reload`. No extra configuration is required. The panel reports whether assets were injected on the request (`lateCollect` reads `_nowo_hot_reload_injected`).

