# Upgrading

## Table of contents

- [From nothing → 1.0.0](#from-nothing--100)

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
