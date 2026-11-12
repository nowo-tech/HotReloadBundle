# Usage

This bundle injects FrankenPHP Hot Reload **client** assets into HTML. The **server** side must enable Mercure and `hot_reload` in the Caddyfile — start with **[Environment setup](ENVIRONMENT.md)** (checklist, Docker, `FRANKENPHP_HOT_RELOAD`, troubleshooting). Official docs: [FrankenPHP Hot Reload](https://frankenphp.dev/docs/hot-reload/) · [dunglas/frankenphp-hot-reload](https://github.com/dunglas/frankenphp-hot-reload).

**Dev only.** Do not enable `hot_reload` or this bundle in production. See [Security](SECURITY.md).

## Table of contents

- [Diagnose setup (`nowo:hot-reload:check`)](#diagnose-setup-nowohot-reloadcheck)
- [Auto-inject (default)](#auto-inject-default)
- [Multi-tab client modes](#multi-tab-client-modes)
- [Twig manual inject](#twig-manual-inject)
- [Caddyfile](#caddyfile)
- [Optional: explicit Mercure URL](#optional-explicit-mercure-url)
- [Preserve toolbar / custom DOM](#preserve-toolbar--custom-dom)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)
  - [Overridable subpaths](#overridable-subpaths)
  - [Procedure](#procedure)
- [Inject event](#inject-event)
- [Standard Symfony host](#standard-symfony-host)
- [DDD / hexagonal host](#ddd--hexagonal-host)

## Diagnose setup (`nowo:hot-reload:check`)

The bundle validates FrankenPHP / Caddy / YAML setup in two places:

```bash
php bin/console nowo:hot-reload:check
php bin/console nowo:hot-reload:check --caddyfile=docker/frankenphp/Caddyfile
php bin/console nowo:hot-reload:check --json --strict
```

Each row is **pass / fail / warn / info / skip**, with a “what to do” line when something is missing (for example `php_server { hot_reload }`, `mercure { anonymous }`, `mercure_url`, or `{{ nowo_hot_reload_assets() }}`).

`FRANKENPHP_HOT_RELOAD` is injected by FrankenPHP on **HTTP** requests only. A **warn** on that row from the CLI is expected. Confirm the same checklist on the Web Debug Toolbar **Hot Reload** profiler panel after loading an HTML page.

Exit code `1` when any check **fails**. `--strict` also fails on warnings. How to fix each row: [Environment setup](ENVIRONMENT.md#troubleshooting).

## Auto-inject (default)

With `auto_inject: true` and a successful render gate (`enabled` + Mercure URL / env, or `require_frankenphp_env: false`), `HotReloadResponseSubscriber` runs on the main HTML response and inserts the snippet:

1. Before `</head>` when present
2. Else before `</body>`
3. Else appends to the body

It skips non-HTML responses, empty bodies, pages that already contain `data-nowo-hot-reload`, and paths matching `ignore_path_prefixes` (default `/_wdt` and `/_profiler`, so the Web Debug Toolbar fragment is not rewritten).

Injected content (when Idiomorph is on):

- `<meta name="frankenphp-hot-reload:url" content="…">`
- Idiomorph `<script>`
- frankenphp-hot-reload `<script type="module">`
- Optional preserve boot script for `preserve_selectors`


## Multi-tab client modes

Browsers limit ~6 concurrent **HTTP/1.1** connections per origin. Each Mercure `EventSource` holds one slot, so many admin tabs can stall navigation.

Set `nowo_hot_reload.client_mode`:

| Mode | Multi-tab | Notes |
| --- | --- | --- |
| `cdn` (default) | Risky on HTTP/1.1 | Upstream frankenphp-hot-reload ESM (BC default) |
| `visibility` | Safe | SSE only while the tab is visible |
| `shared_worker` | **Recommended** | One SharedWorker SSE shared by all tabs; hidden tabs queue morphs |
| `always` | Risky on HTTP/1.1 | SSE always open per tab |

HTTP/2 (local TLS) is an **infrastructure** alternative that multiplexes streams so `cdn`/`always` stay viable — it is not a `client_mode` value. The Web Debug Toolbar **Hot Reload** panel **Help** tab includes a comparison table and validates the active mode against the request protocol.

```yaml
# config/packages/dev/nowo_hot_reload.yaml
when@dev:
    nowo_hot_reload:
        enabled: true
        client_mode: shared_worker
```

Bundle client / worker scripts are served at `/_nowo/hot-reload/client.js` and `/_nowo/hot-reload/shared-worker.js` (same origin; CSP `worker-src 'self'`).

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

## Caddyfile

Copy-paste classic and worker Caddyfiles, Docker rules, and `FRANKENPHP_HOT_RELOAD` behaviour are in **[Environment setup](ENVIRONMENT.md)**.

Minimum directives:

- `order mercure after encode` (global options)
- `mercure { anonymous }`
- `php_server { hot_reload }`
- Worker mode: `worker { file …; watch }`
- Worker with Symfony Runtime `reset_kernel: false` is supported (see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md))

Do **not** put `FRANKENPHP_HOT_RELOAD` in `.env`. FrankenPHP sets it on HTTP requests when `hot_reload` is enabled.

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

## Inject event

`Nowo\HotReloadBundle\Event\HotReloadInjectEvent` is dispatched right before the snippet is written into an HTML
response. Since 1.7.0 its accessors carry scalars only (REQ-ARCH-001 rule 3):

| Accessor | Since | Use |
|---|---|---|
| `getPath(): string` | 1.7.0 | request path (path info, no query string) |
| `getContentType(): ?string` | 1.7.0 | response `Content-Type` header |
| `getBody(): string` / `setBody(string)` | 1.7.0 | HTML body; the snippet is injected into the body you leave |
| `getSnippet(): string` / `setSnippet(string)` | 1.0 | the snippet; `setSnippet('')` cancels the injection |
| `getRequest()` / `getResponse()` | 1.0, **deprecated 1.7.0** | removed in 2.0 (see [UPGRADING](UPGRADING.md#to-170)) |

```php
use Nowo\HotReloadBundle\Event\HotReloadInjectEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class SkipAdminHotReload
{
    public function __invoke(HotReloadInjectEvent $event): void
    {
        if (str_starts_with($event->getPath(), '/admin')) {
            $event->setSnippet('');
        }
    }
}
```

Response headers (CSP) are not part of the event: use the `csp_*` options (see [CSP.md](CSP.md)) or your own
`kernel.response` listener. Dumping the event (VarDumper, `var_export()`, `(array)`, `serialize()`) shows only the
path, the content type and the snippet: the request, the response and the body are kept outside its properties.
To unit test a listener, build the event with `HotReloadInjectEvent::fromContent($path, $contentType, $body, $snippet)`.

## Standard Symfony host

Zero configuration beyond `config/packages/dev/nowo_hot_reload.yaml`: register the bundle for `dev` only, keep
`auto_inject: true` (or call `{{ nowo_hot_reload_assets() }}` in your base layout with `auto_inject: false`) and,
when needed, listen to the [inject event](#inject-event) or override the templates (see
[Overriding templates](#overriding-templates-req-twig-001)).

## DDD / hexagonal host

HotReloadBundle is **developer tooling at the presentation edge**: in development it adds the FrankenPHP hot-reload
client to HTML responses and adjusts the CSP header. It has no entities, no Doctrine dependency and no services your
application code calls, so nothing in your domain or application layer depends on it. The configuration is the same
as for a standard Symfony host.

- The only extension point is the [inject event](#inject-event). Since 1.7.0 a listener needs no HttpFoundation type
  (`getPath()`, `getContentType()`, `getBody()` / `setBody()`, `setSnippet()`); keep it in your UI / infrastructure
  layer anyway, never in the domain.
- There are no ports to implement, no IDs to map and no domain events. If you enforce layer rules (e.g. deptrac), the
  `Nowo\HotReloadBundle\` namespace belongs to the presentation/dev-tooling layer.
- Tested: `tests/Integration/Kernel/DddHostKernelTest.php` boots a host kernel whose listener uses only the
  framework-free accessors and serves 600 requests through one kept-alive `http_kernel` (no services resetter).

There are no known limitations for hexagonal applications.
