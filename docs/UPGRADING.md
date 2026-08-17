# Upgrading

## Table of contents

- [From 1.1.0 → 1.2.0](#from-110--120)
- [From 1.0.0 → 1.1.0](#from-100--110)
- [From nothing → 1.0.0](#from-nothing--100)

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
