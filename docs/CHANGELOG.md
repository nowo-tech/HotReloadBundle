# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.3.2] - 2026-08-17](#132---2026-08-17)
- [[1.3.1] - 2026-08-17](#131---2026-08-17)
- [[1.3.0] - 2026-08-17](#130---2026-08-17)
- [[1.2.1] - 2026-08-17](#121---2026-08-17)
- [[1.2.0] - 2026-08-17](#120---2026-08-17)
- [[1.1.0] - 2026-08-17](#110---2026-08-17)
- [[1.0.0] - 2026-08-17](#100---2026-08-17)

## [Unreleased]

## [1.3.2] - 2026-08-17

### Fixed

- Demo smoke: Composer install retries, `--no-dev`, and GitHub token via `COMPOSER_AUTH` so GitHub zipball 504/429 does not fail REQ-TEST-011.

## [1.3.1] - 2026-08-17

### Added

- **Twig Extra** (REQ-TWIG-004) — runtime `twig/extra-bundle` and `twig/string-extra`; `make check-twig-extra` is part of `release-check`. The Symfony 8 demo declares Extra in `composer.json` and enables `TwigExtraBundle`.

### Changed

- README compatibility blockquote and GitHub stars badge kebab-case (REQ-DOCS-019). GitHub About website/topics (REQ-DOCS-018).

## [1.3.0] - 2026-08-17

### Changed

- Declared and tested compatibility with **Symfony 7.4+** and **8.0–8.2** (`symfony/*` `^7.4 || ^8.0`). CI matrix is now `7.4`, `8.0`, `8.1`, and `8.2` (dropped leftover 6.4 / 7.0 cells that could not install). The Symfony 8 demo targets **8.2**.

### Fixed

- Twig namespace is **`NowoHotReloadBundle`** (REQ-TWIG-002). `TwigPathsPass` registers `@NowoHotReloadBundle/...` on the native loader (`addPath` after optional `prependPath` for `templates/bundles/NowoHotReloadBundle/`). The 1.2.1 Symfony-default `@NowoHotReload/...` remains a BC alias. Profiler template: `@NowoHotReloadBundle/Collector/hot_reload.html.twig`.
- PHP **8.2** parse errors from typed class constants (`public const string`); constants are untyped so the 7.4 + PHP 8.2 CI cell and coverage job pass.
- Demo smoke: install Composer dependencies with `docker compose run` **before** starting FrankenPHP worker mode, so the php service does not exit before `composer install`.

## [1.2.1] - 2026-08-17

### Fixed

- Profiler template namespace: use `@NowoHotReload/…` (Symfony Twig namespace for `NowoHotReloadBundle`) instead of `@NowoHotReloadBundle/…`, which caused "template does not exist" for the Web Debug Toolbar panel.

## [1.2.0] - 2026-08-17

### Added

- **Web Debug Toolbar / Profiler** — `HotReloadDataCollector` (`nowo_hot_reload`) shows status (active/ready/idle/disabled), Mercure URL, injection flag, preserve selectors, and CSP settings. Template: `@NowoHotReload/Collector/hot_reload.html.twig`.

## [1.1.0] - 2026-08-17

### Added

- **CSP support** — `csp_nonce_request_attribute` stamps the host CSP nonce on the inline preserve boot script; `csp_augment_script_src` / `csp_script_src_hosts` can append CDN origins to an existing response `Content-Security-Policy` `script-src`. Docs: [CSP.md](CSP.md).
- **`HotReloadInjectEvent`** — dispatched before HTML injection so hosts can mutate the snippet or headers.
- **`preserve_observe`** (default `true`) — preserve boot uses `MutationObserver` for late-injected Web Debug Toolbar nodes.
- **`allow_production`** (default `false`) — rejects `enabled: true` when `kernel.environment` is `prod`.
- HTML sniffing when `Content-Type` is missing/empty (body contains `<html>`).

### Changed

- Default CDN URLs are **version-pinned**: Idiomorph `@0.7.4`, frankenphp-hot-reload `@1.0.1/+esm`.
- Default `preserve_selectors`: `[id^="sfwdt"]`, `.sf-toolbar`, `.sf-minitoolbar`.
- Flex recipe registers the bundle for **`dev` / `test` only** (was `all`).

### Security

- Production enablement blocked by default (`allow_production: false`). See [SECURITY.md](SECURITY.md) and [CSP.md](CSP.md).

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

[1.3.2]: https://github.com/nowo-tech/HotReloadBundle/releases/tag/v1.3.2
[1.3.1]: https://github.com/nowo-tech/HotReloadBundle/releases/tag/v1.3.1
[1.3.0]: https://github.com/nowo-tech/HotReloadBundle/releases/tag/v1.3.0
[1.2.1]: https://github.com/nowo-tech/HotReloadBundle/releases/tag/v1.2.1
[1.2.0]: https://github.com/nowo-tech/HotReloadBundle/releases/tag/v1.2.0
[1.1.0]: https://github.com/nowo-tech/HotReloadBundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/HotReloadBundle/releases/tag/v1.0.0
[Unreleased]: https://github.com/nowo-tech/HotReloadBundle/compare/v1.3.1...HEAD
