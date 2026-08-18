# Installation

## Requirements

- PHP `>=8.1` (<8.6). Symfony **8.x** requires **PHP 8.4+**.
- Symfony **7.4+** and **8.0–8.2** (`symfony/config`, `symfony/dependency-injection`, `symfony/http-foundation`, `symfony/http-kernel` with `^7.4 || ^8.0`). Symfony **8.2** is under development (`8.2.x-dev`) until November 2026; apps on that line need `minimum-stability: dev` (or `8.2.*@dev`) until the stable tag.
- FrankenPHP with Hot Reload enabled in the Caddyfile (Mercure + `hot_reload`). See [Environment setup](ENVIRONMENT.md).
- `twig/twig` — pulled in via `twig/extra-bundle` (REQ-TWIG-004). Required for `{{ nowo_hot_reload_assets() }}` and the Web Debug Toolbar panel.
- Host apps that render `@NowoHotReloadBundle/...` templates must install **`twig/extra-bundle`** and **`twig/string-extra`** and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle` (Flex usually does this). The package `release-check` runs `make check-twig-extra`.

## Composer

Prefer a **dev** dependency (this feature must not run in production):

```bash
composer require nowo-tech/hot-reload-bundle --dev
```

## Enable the bundle

### With Symfony Flex

The recipe enables the bundle and adds `config/packages/nowo_hot_reload.yaml`. Restrict the bundle to `dev` / `test` environments.

### Without Flex

1. Register the bundle in `config/bundles.php` (dev/test only):

```php
return [
    // ...
    Nowo\HotReloadBundle\NowoHotReloadBundle::class => ['dev' => true, 'test' => true],
];
```

2. Create `config/packages/dev/nowo_hot_reload.yaml` (or `config/packages/nowo_hot_reload.yaml`):

```yaml
nowo_hot_reload:
    enabled: true
    auto_inject: true
    require_frankenphp_env: true
    idiomorph: true
    preserve_selectors:
        - '[id^="sfwdt"]'
        - '.sf-toolbar'
        - '.sf-minitoolbar'
```

3. Configure the FrankenPHP environment (Caddy `mercure` + `hot_reload`, worker `watch`). Follow **[Environment setup](ENVIRONMENT.md)** — do not put `FRANKENPHP_HOT_RELOAD` in `.env`.
4. Validate the setup: `php bin/console nowo:hot-reload:check` (the same checklist appears on the Web Debug Toolbar **Hot Reload** panel).

## Next steps

- [**Environment setup**](ENVIRONMENT.md) — Caddyfile, Docker, render gate, troubleshooting
- [Configuration](CONFIGURATION.md)
- [Usage](USAGE.md)
