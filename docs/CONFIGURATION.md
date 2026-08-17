# Configuration

All options live under `nowo_hot_reload`:

| Option | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Master switch. When `false`, nothing is injected even if `FRANKENPHP_HOT_RELOAD` is set. |
| `auto_inject` | `true` | When `true`, `HotReloadResponseSubscriber` injects assets into HTML responses. |
| `require_frankenphp_env` | `true` | When `true`, inject only if `mercure_url` is set or `$_SERVER['FRANKENPHP_HOT_RELOAD']` is present. When `false`, assets may render with an empty Mercure URL. |
| `mercure_url` | `null` | Optional Mercure hub URL. When `null`, uses `$_SERVER['FRANKENPHP_HOT_RELOAD']` when present. |
| `idiomorph` | `true` | When `true`, include the Idiomorph classic script for DOM morphing. |
| `idiomorph_script_url` | `https://cdn.jsdelivr.net/npm/idiomorph` | URL of the Idiomorph script. |
| `hot_reload_script_url` | `https://cdn.jsdelivr.net/npm/frankenphp-hot-reload/+esm` | URL of the frankenphp-hot-reload ESM module. |
| `preserve_selectors` | `['#sfwdt', '.sf-toolbar']` | CSS selectors that receive `data-frankenphp-hot-reload-preserve` (e.g. Symfony Web Debug Toolbar). |

## Render gate

Assets are rendered only when **all** of the following hold:

1. `enabled` is `true`
2. Either `mercure_url` / `FRANKENPHP_HOT_RELOAD` resolves to a non-empty URL, **or** `require_frankenphp_env` is `false`

Injected markup is marked with `data-nowo-hot-reload` so the subscriber does not inject twice.

Performance implications (one HTML rewrite when the gate is open; zero body work when not) are described in [Performance](PERFORMANCE.md).
