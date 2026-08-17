# Hot Reload Bundle Demo

Runnable FrankenPHP demo:

- `symfony8` — Symfony **8.1**, PHP **8.4+** (http://localhost:8011)

## Quick start

```bash
make up-symfony8
```

The demo includes:

- FrankenPHP with Caddy (HTTP on `:80` inside container). Default **`FRANKENPHP_MODE=worker`**; see [docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md) for classic vs worker and Hot Reload Caddyfile requirements.
- Web Profiler enabled in `dev`
- **Nowo HotReloadBundle** enabled in `dev` / `test`
- Dedicated `Makefile` (`demo/symfony8/Makefile`)

For Mercure + `hot_reload` (+ worker `watch`) configuration, see [docs/USAGE.md](../docs/USAGE.md).

## Release checks

```bash
make release-check
```

This runs PHPUnit in the demo (smoke), updates the path bundle, and verifies startup + HTTP healthcheck.
