# Usage

This bundle injects FrankenPHP Hot Reload **client** assets into HTML. The **server** side must enable Mercure and `hot_reload` in the Caddyfile. Official docs: [FrankenPHP Hot Reload](https://frankenphp.dev/docs/hot-reload/) · [dunglas/frankenphp-hot-reload](https://github.com/dunglas/frankenphp-hot-reload).

**Dev only.** Do not enable `hot_reload` or this bundle in production. See [Security](SECURITY.md).

## Auto-inject (default)

With `auto_inject: true` and a successful render gate (`enabled` + Mercure URL / env, or `require_frankenphp_env: false`), `HotReloadResponseSubscriber` runs on the main HTML response and inserts the snippet:

1. Before `</head>` when present
2. Else before `</body>`
3. Else appends to the body

It skips non-HTML responses, empty bodies, and pages that already contain `data-nowo-hot-reload`.

Injected content (when Idiomorph is on):

- `<meta name="frankenphp-hot-reload:url" content="…">`
- Idiomorph `<script>`
- frankenphp-hot-reload `<script type="module">`
- Optional preserve boot script for `preserve_selectors`

## Twig manual inject

Requires `twig/twig`. Disable auto-inject and place the helper in your base layout:

```yaml
# config/packages/dev/nowo_hot_reload.yaml
nowo_hot_reload:
    enabled: true
    auto_inject: false
```

```twig
{# templates/base.html.twig #}
<head>
    …
    {{ nowo_hot_reload_assets() }}
</head>
```

The Twig function returns the same HTML as auto-inject (or an empty string when the render gate fails).

## Caddyfile — classic (`php_server` + hot reload)

```caddyfile
{
	frankenphp
	order mercure after encode
}

:80 {
	root * /app/public
	encode zstd br gzip

	mercure {
		anonymous
	}

	php_server {
		hot_reload
	}
}
```

FrankenPHP sets `FRANKENPHP_HOT_RELOAD` for PHP when `hot_reload` is enabled; the bundle reads that by default.

## Caddyfile — worker mode + watch

In worker mode, add a `worker` block with `watch` so file changes trigger reloads:

```caddyfile
{
	frankenphp
	order mercure after encode
}

:80 {
	root * /app/public
	encode zstd br gzip

	mercure {
		anonymous
	}

	php_server {
		hot_reload
		worker {
			file ./public/index.php
			watch
		}
	}
}
```

Adjust `file` / `watch` paths to your project layout. See the [official Hot Reload guide](https://frankenphp.dev/docs/hot-reload/) for current directives.

## Optional: explicit Mercure URL

If the env var is not available in your process, set it in config:

```yaml
nowo_hot_reload:
    mercure_url: 'http://localhost/.well-known/mercure'
```

## Preserve toolbar / custom DOM

Default `preserve_selectors` (`[id^="sfwdt"]`, `.sf-toolbar`, `.sf-minitoolbar`) mark the Symfony Web Debug Toolbar with `data-frankenphp-hot-reload-preserve` so morphing keeps it. Add more selectors as needed:

```yaml
nowo_hot_reload:
    preserve_selectors:
        - '[id^="sfwdt"]'
        - '.sf-toolbar'
        - '.sf-minitoolbar'
        - '#my-sticky-widget'
```

See [Performance](PERFORMANCE.md) for injection cost when the gate is open vs closed.
