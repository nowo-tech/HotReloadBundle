# Hot Reload Bundle

[![CI](https://github.com/nowo-tech/HotReloadBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/HotReloadBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/hot-reload-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/hot-reload-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/hot-reload-bundle.svg)](https://packagist.org/packages/nowo-tech/hot-reload-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/HotReloadBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/HotReloadBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/hot-reload-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/HotReloadBundle) so more developers can find it.

**Hot Reload Bundle** — Symfony integration for [FrankenPHP Hot Reload](https://frankenphp.dev/docs/hot-reload/) ([dunglas/frankenphp-hot-reload](https://github.com/dunglas/frankenphp-hot-reload)). When enabled, it injects the Mercure hub meta tag, optional Idiomorph, and the frankenphp-hot-reload ESM module into HTML responses so the browser can morph or reload after PHP (and watched) file changes. **Dev-only** · PHP 8.1+ · Symfony **7.4** or **8.0+**.

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly** (pair with `worker { …; watch }` in your Caddyfile).

## Features

- **Auto-inject** — `HotReloadResponseSubscriber` inserts assets before `</head>` (else `</body>`) on HTML responses.
- **Twig helper** — `{{ nowo_hot_reload_assets() }}` for manual layouts when `auto_inject` is off.
- **Env-aware** — Renders only when `enabled` and (`mercure_url` or `FRANKENPHP_HOT_RELOAD` is set, or `require_frankenphp_env: false`).
- **Idiomorph** — Optional DOM morphing instead of a full page reload (on by default).
- **Preserve selectors** — Marks Symfony Web Debug Toolbar (`[id^="sfwdt"]`, `.sf-toolbar`, `.sf-minitoolbar`) with `data-frankenphp-hot-reload-preserve` (optional `MutationObserver`).
- **CSP-aware** — Optional request-attribute nonce on the preserve boot script; can augment existing `Content-Security-Policy` `script-src` for jsDelivr (see [docs/CSP.md](docs/CSP.md)).

## Installation

```bash
composer require nowo-tech/hot-reload-bundle --dev
```

With **Symfony Flex**, the recipe registers the bundle and adds config. Without Flex, see [docs/INSTALLATION.md](docs/INSTALLATION.md).

**Manual registration** in `config/bundles.php` (prefer `dev` / `test` only):

```php
return [
  // ...
  Nowo\HotReloadBundle\NowoHotReloadBundle::class => ['dev' => true, 'test' => true],
];
```

Server side: enable Mercure (`anonymous`) and `php_server { hot_reload }` in your Caddyfile. For worker mode, add `worker { file …; watch }`. See [Usage](docs/USAGE.md).

## Requirements

- PHP `>=8.1` (<8.6); **Symfony 8.x** requires **PHP 8.4+**
- Symfony **7.4** or **8.0+** (`symfony/*` `^7.4 || ^8.0`)
- FrankenPHP with Hot Reload + Mercure configured in the Caddyfile (dev)
- `twig/twig` optional — required only for `{{ nowo_hot_reload_assets() }}`

## Configuration

```yaml
nowo_hot_reload:
  enabled: true
  auto_inject: true
  require_frankenphp_env: true
  allow_production: false
  # mercure_url: null  # defaults to $_SERVER['FRANKENPHP_HOT_RELOAD']
  idiomorph: true
  # idiomorph_script_url: 'https://cdn.jsdelivr.net/npm/idiomorph@0.7.4'
  # hot_reload_script_url: 'https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm'
  preserve_selectors:
    - '[id^="sfwdt"]'
    - '.sf-toolbar'
    - '.sf-minitoolbar'
  # csp_nonce_request_attribute: '_csp_nonce'
  csp_augment_script_src: true
```

## Usage

With `auto_inject: true` (default), no template changes are needed when FrankenPHP sets `FRANKENPHP_HOT_RELOAD` (or you set `mercure_url`).

Manual Twig injection:

```twig
{{ nowo_hot_reload_assets() }}
```

Official references:

- [FrankenPHP Hot Reload docs](https://frankenphp.dev/docs/hot-reload/)
- [dunglas/frankenphp-hot-reload](https://github.com/dunglas/frankenphp-hot-reload)

## Demo

- `demo/symfony8` — Symfony **8.1** (PHP **8.5**), host port **8011** by default (`PORT` in `.env`)

The demo runs **FrankenPHP + Caddy** in Docker. See [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md).

Global demo commands: `make -C demo help` (e.g. `make -C demo up-symfony8`).

## Development

```bash
make up
make install
make test
make cs-check
make phpstan
make release-check
```

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [CSP](docs/CSP.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [Performance](docs/PERFORMANCE.md)
- [Demo (FrankenPHP)](docs/DEMO-FRANKENPHP.md)
- [GitHub CI notes](docs/GITHUB_CI.md)

## Tests and coverage

- Tests: PHPUnit (PHP)
- PHP: 100%

## License and author

MIT · [Nowo.tech](https://nowo.tech) · [Héctor Franco Aceituno](https://github.com/HecFranco)
