# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.0.0] - 2026-08-17](#100---2026-08-17)

## [Unreleased]

## [1.0.0] - 2026-08-17

### Added

- **Initial release** of [`nowo-tech/hot-reload-bundle`](https://packagist.org/packages/nowo-tech/hot-reload-bundle) — Symfony integration for [FrankenPHP Hot Reload](https://frankenphp.dev/docs/hot-reload/) ([dunglas/frankenphp-hot-reload](https://github.com/dunglas/frankenphp-hot-reload)).
- **`HotReloadAssets`** — builds meta `frankenphp-hot-reload:url`, optional Idiomorph, frankenphp-hot-reload ESM module, and preserve-selector boot script (`data-nowo-hot-reload`, `data-frankenphp-hot-reload-preserve`).
- **`HotReloadResponseSubscriber`** — auto-injects assets before `</head>` (else `</body>`) on HTML main responses when `auto_inject` is enabled.
- **Twig** — `{{ nowo_hot_reload_assets() }}` for manual layout inclusion when `auto_inject` is `false`.
- **Configuration** (`nowo_hot_reload`): `enabled`, `auto_inject`, `require_frankenphp_env`, `mercure_url`, `idiomorph`, `idiomorph_script_url`, `hot_reload_script_url`, `preserve_selectors` (defaults `#sfwdt`, `.sf-toolbar`).
- **Render gate** — assets render only when `enabled` and (`mercure_url` / `FRANKENPHP_HOT_RELOAD` is set, or `require_frankenphp_env: false`).
- **Demo** — FrankenPHP Docker demo for Symfony 8 (`demo/symfony8`, host port **8011**) with Mercure + `hot_reload` (+ `worker.watch` in worker mode).
- **Tooling** — PHPUnit (100% line coverage), PHPStan (+ FrankenPHP rules), PHP-CS-Fixer, Rector, GitHub Actions CI / release workflow, Spec Kit baseline.
- PHP `>=8.1` `<8.6`, Symfony `^7.4 || ^8.0`.

### Security

- Documented as **development-only**; do not enable FrankenPHP `hot_reload` or register this bundle in production. See [SECURITY.md](SECURITY.md).

[1.0.0]: https://github.com/nowo-tech/HotReloadBundle/releases/tag/v1.0.0
[Unreleased]: https://github.com/nowo-tech/HotReloadBundle/compare/v1.0.0...HEAD
