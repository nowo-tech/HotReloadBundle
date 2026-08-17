# Upgrading

## Table of contents

- [From 1.3.0 → 1.3.1](#from-130--131)
- [From 1.2.1 → 1.3.0](#from-121--130)
- [From 1.2.0 → 1.2.1](#from-120--121)
- [From 1.1.0 → 1.2.0](#from-110--120)
- [From 1.0.0 → 1.1.0](#from-100--110)
- [From nothing → 1.0.0](#from-nothing--100)

## From 1.3.0 → 1.3.1

Adds Twig Extra as a runtime dependency (REQ-TWIG-004).

```bash
composer require nowo-tech/hot-reload-bundle:^1.3.1 --dev
```

### Checklist

1. Hosts that render `@NowoHotReloadBundle/...` templates (profiler panel or `{{ nowo_hot_reload_assets() }}`) must have `twig/extra-bundle` and `twig/string-extra` installed and `Twig\Extra\TwigExtraBundle\TwigExtraBundle` enabled (Flex usually does this).
2. No config changes.

## From 1.2.1 → 1.3.0

Twig namespace is `NowoHotReloadBundle` (REQ-TWIG-002). The Web Profiler template path is `@NowoHotReloadBundle/Collector/hot_reload.html.twig`.

Supported Symfony range is **7.4+** and **8.0–8.2** (`^7.4 || ^8.0`). Symfony 6.x and 7.0–7.3 are not supported.

```bash
composer require nowo-tech/hot-reload-bundle:^1.3 --dev
```

### Checklist

1. If you referenced `@NowoHotReload/...` (1.2.1 profiler path), switch to `@NowoHotReloadBundle/...`. The old namespace stays registered as a BC alias for this major.
2. Template overrides belong in `templates/bundles/NowoHotReloadBundle/` (see [USAGE.md](USAGE.md#overriding-templates-req-twig-001)).
3. Hosts on Symfony **8.2** (including `8.2.x-dev`) can install this bundle with the existing `^7.4 || ^8.0` constraints; Symfony 8.x still requires PHP **8.4+**.
4. No config changes.

## From 1.2.0 → 1.2.1

Fixes the Web Profiler template path (`@NowoHotReload/…`).

```bash
composer require nowo-tech/hot-reload-bundle:^1.2.1 --dev
```

No config changes.

## From 1.1.0 → 1.2.0

Adds a Symfony Web Debug Toolbar / Profiler panel for Hot Reload.

```bash
composer require nowo-tech/hot-reload-bundle:^1.2 --dev
```

### Checklist

1. Ensure `symfony/web-profiler-bundle` is enabled in `dev` (usual for Symfony apps).
2. Open any HTML page with the toolbar — look for the **Hot Reload** block (`on` / `ready` / `idle` / `off`).
3. No config changes required; collector registers automatically when Twig is available.

## From 1.0.0 → 1.1.0

CSP-aware Hot Reload client injection, safer prod defaults, and pinned CDN URLs.

```bash
composer require nowo-tech/hot-reload-bundle:^1.1 --dev
```

### Checklist

1. **Bundle envs** — ensure `config/bundles.php` registers `NowoHotReloadBundle` for **`dev` / `test` only** (Flex recipe now does this; older installs with `all` should narrow it).
2. **CSP (recommended)** — set `csp_nonce_request_attribute` to your host nonce request attribute, or rely on `csp_augment_script_src` when the response already has a CSP header. See [CSP.md](CSP.md).
3. **Optional config** — review new defaults (`preserve_selectors`, pinned `idiomorph_script_url` / `hot_reload_script_url`, `preserve_observe`, `allow_production: false`).
4. **Listeners** — optional: subscribe to `Nowo\HotReloadBundle\Event\HotReloadInjectEvent`.

No Doctrine migrations. No production runtime impact when the bundle stays out of `prod`.

## From nothing → 1.0.0

Initial public release. There are no prior versions and no migration steps.

### Install

```bash
composer require nowo-tech/hot-reload-bundle --dev
```

### Checklist

1. Register the bundle for **`dev` / `test` only** (never `prod`).
2. Add `config/packages/dev/nowo_hot_reload.yaml` (or rely on the Flex recipe).
3. Enable Mercure (`anonymous`) and `php_server { hot_reload }` in the Caddyfile.
4. In FrankenPHP **worker** mode, add `worker { file …; watch }` so PHP code changes reload the worker.
5. Confirm `FRANKENPHP_HOT_RELOAD` is set (or configure `mercure_url`) and that HTML responses include `data-nowo-hot-reload`.

See [Installation](INSTALLATION.md), [Configuration](CONFIGURATION.md), and [Usage](USAGE.md).
