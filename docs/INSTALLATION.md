# Installation

## Requirements

- PHP `>=8.1` (<8.6). Symfony **8.x** requires **PHP 8.4+**.
- Symfony **7.4** or **8.0+** (`symfony/config`, `symfony/dependency-injection`, `symfony/http-foundation`, `symfony/http-kernel` with `^7.4 || ^8.0`).
- FrankenPHP with Hot Reload enabled in the Caddyfile (Mercure + `hot_reload`). See [Usage](USAGE.md).
- `twig/twig` optional — required only for `{{ nowo_hot_reload_assets() }}`.

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
        - '#sfwdt'
        - '.sf-toolbar'
```

3. Configure the FrankenPHP / Caddyfile for Mercure + `hot_reload` (and `worker { …; watch }` in worker mode). See [Usage](USAGE.md).

## Next steps

- [Configuration](CONFIGURATION.md)
- [Usage](USAGE.md)
