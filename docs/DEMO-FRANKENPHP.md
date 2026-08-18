# Demo with FrankenPHP

This bundle includes a runnable demo with FrankenPHP in:

- `demo/symfony8` — Symfony **8.2** (`8.2.x-dev` until the November 2026 stable; FrankenPHP PHP **8.5**, REQ-DEMO-010)

The demo uses:

- Caddy on HTTP `:80` inside the container
- **`Caddyfile`**: **worker** mode (`php_server { worker ... }`) — selected when `FRANKENPHP_MODE=worker` (**default**)
- **`Caddyfile.dev`**: classic `php_server` — selected when `FRANKENPHP_MODE=classic`

For Hot Reload to work end-to-end, the Caddyfile must enable Mercure (`anonymous`) and `php_server { hot_reload }` (and in worker mode `worker { file …; watch }`). Canonical guide: [Environment setup](ENVIRONMENT.md). Also [Usage](USAGE.md) and the [official FrankenPHP Hot Reload docs](https://frankenphp.dev/docs/hot-reload/).

**Default development stack:** `docker-compose.yml` sets **`APP_ENV=dev`**, **`APP_DEBUG=1`**, and **`FRANKENPHP_MODE=worker`**, and mounts **`docker/php-dev.ini`**. Use `FRANKENPHP_MODE=classic` when you need one PHP process per request / simpler first-boot before `composer install`.

## Table of contents

- [Quick start](#quick-start)
- [Development stack in demos](#development-stack-in-demos)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Hot Reload checklist](#hot-reload-checklist)
- [Production](#production)
- [Troubleshooting](#troubleshooting)
- [Demo smoke (REQ-TEST-011)](#demo-smoke-req-test-011)

## Quick start

From the bundle root:

```bash
make -C demo up-symfony8
```

Then open `http://localhost:8011`.

View page source and confirm meta `frankenphp-hot-reload:url` / scripts with `data-nowo-hot-reload` when Hot Reload is configured and the env gate is open.

## Development stack in demos

The demo includes:

- **Symfony Debug** (`symfony/debug-bundle`)
- **Symfony Web Profiler** (`symfony/web-profiler-bundle`)
- **`APP_DEBUG=1`** in `.env.example`
- **Nowo Hot Reload** (`nowo-tech/hot-reload-bundle`) for `dev` / `test`

Example `config/bundles.php`:

```php
<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class     => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class               => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class             => ['dev' => true, 'test' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Nowo\HotReloadBundle\NowoHotReloadBundle::class           => ['dev' => true, 'test' => true],
];
```

## Switching classic vs worker (`FRANKENPHP_MODE`)

The demo selects the FrankenPHP runtime via **`FRANKENPHP_MODE`** in `.env` / `.env.example` (not a Dockerfile `ENV`):

| Value | Behaviour |
| --- | --- |
| **`worker`** (default) | Keep the worker Caddyfile (`php_server { worker ... }`) |
| **`classic`** | Entrypoint copies `Caddyfile.dev` (plain `php_server`) |

Compose passes `FRANKENPHP_MODE=${FRANKENPHP_MODE:-worker}` into the PHP service. After changing `.env`, run `docker compose up -d` (or `make up`) so the container is **recreated** — a plain `restart` does not reload env. No image rebuild is required.

## Hot Reload checklist

Follow [Environment setup](ENVIRONMENT.md#minimum-checklist). For this demo:

1. Caddyfile: `mercure { anonymous }` + `php_server { hot_reload }` (worker: also `worker { …; watch }`).
2. Bundle enabled for `dev` with `nowo_hot_reload.enabled: true`.
3. `FRANKENPHP_HOT_RELOAD` set by FrankenPHP on HTTP (not in `.env`), or `mercure_url` in config.
4. Validate: `docker compose exec php php bin/console nowo:hot-reload:check --caddyfile=docker/frankenphp/Caddyfile` (or `Caddyfile.dev` in classic mode).
5. Load an HTML page — assets appear before `</head>` when `auto_inject` is true; the profiler **Hot Reload** panel shows Environment checks.

## Production

Do **not** enable FrankenPHP `hot_reload` or this bundle in production. Keep `FRANKENPHP_MODE=worker` without Hot Reload for production-like runs; set `APP_ENV=prod` / `APP_DEBUG=0` as needed.

## Troubleshooting

- If app does not respond, run `make -C demo/symfony8 logs`.
- If routes/config changed, run `make -C demo/symfony8 cache-clear`.
- If dependencies are outdated, run `make -C demo/symfony8 update-bundle`.
- Unknown `FRANKENPHP_MODE` values fail fast in `docker/entrypoint.sh`.
- No Hot Reload assets: run `php bin/console nowo:hot-reload:check` and see [Environment setup — troubleshooting](ENVIRONMENT.md#troubleshooting). Confirm Mercure + `hot_reload` in the **active** Caddyfile.

## Demo smoke (REQ-TEST-011)

Automated smoke proves the Symfony 8 FrankenPHP demo boots and returns **HTTP 200**:

```bash
make demo-smoke
```

This runs `make -C demo/symfony8 up`, then `curl` against `http://localhost:8011/` (or `PORT` from `.env` / `.env.example`). CI runs the same target via `.github/workflows/demo-smoke.yml` (schedule / tag / workflow_dispatch). For a full demo release check, use `make -C demo release-verify`.
