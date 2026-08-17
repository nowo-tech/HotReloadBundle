# Usage

This bundle injects FrankenPHP Hot Reload **client** assets into HTML. The **server** side must enable Mercure and `hot_reload` in the Caddyfile. Official docs: [FrankenPHP Hot Reload](https://frankenphp.dev/docs/hot-reload/) · [dunglas/frankenphp-hot-reload](https://github.com/dunglas/frankenphp-hot-reload).

**Dev only.** Do not enable `hot_reload` or this bundle in production. See [Security](SECURITY.md).

## Table of contents

- [Auto-inject (default)](#auto-inject-default)
- [Twig manual inject](#twig-manual-inject)
- [Caddyfile — classic (`php_server` + hot reload)](#caddyfile--classic-php_server--hot-reload)
- [Caddyfile — worker mode + watch](#caddyfile--worker-mode--watch)
- [Optional: explicit Mercure URL](#optional-explicit-mercure-url)
- [Preserve toolbar / custom DOM](#preserve-toolbar--custom-dom)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)

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

## Overriding templates (REQ-TWIG-001)

The profiler / Web Debug Toolbar templates use the Twig namespace **`NowoHotReloadBundle`** (REQ-TWIG-002). Application files under `templates/bundles/NowoHotReloadBundle/` **always** win over the copies shipped in the package.

A full-file override **freezes** that `<subpath>`: vendor updates to the same file will not apply until you delete or merge the override. Prefer leaving these files untouched unless you need a surgical change (icon or panel markup).

### Overridable subpaths

| `<subpath>` | Purpose |
| --- | --- |
| `Collector/hot_reload.html.twig` | Web Debug Toolbar / Profiler panel |
| `Icon/hot-reload.svg` | Toolbar and profiler menu icon |

### Procedure

1. Identify the `<subpath>` from the table above (path relative to the bundle `templates/` directory).
2. Create in the application: `templates/bundles/NowoHotReloadBundle/<subpath>` (same relative path and filename).
3. Clear the Symfony / Twig cache if needed: `php bin/console cache:clear`.

Example: to replace the profiler icon, create:

```text
templates/bundles/NowoHotReloadBundle/Icon/hot-reload.svg
```

Logical names in PHP and Twig are `@NowoHotReloadBundle/<subpath>`. The Symfony-stripped namespace `@NowoHotReload/...` remains registered as a BC alias for this major; new overrides and references should use `@NowoHotReloadBundle`.
