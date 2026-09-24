# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/hot-reload-bundle` (`symfony-bundle`) |
| Audited revision | `v1.5.3` |
| Audit date | 2026-09-24 |
| Method | Manual review of every file under `src/` (services, subscribers, data collector, Twig extension, command, DI extension, compiler pass, YAML config) and `NowoHotReloadBundle.php`; PHPStan classic + worker + hardening |
| **Verdict** | ✅ **100% compatible** with FrankenPHP worker mode and **`reset_kernel: false`** (scenario B below) |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `HotReloadAssets`, `HotReloadDiagnostics`, both subscribers and the Twig extension only have `readonly` constructor properties; `HotReloadDataCollector` holds `$data` / `$request` but rewrites them on every `collect()`, clears the request in `lateCollect()`, and implements `ResetInterface` + `kernel.reset` |
| Static properties / `static` locals | ✅ | None. `ClientModeGuide` only has pure static methods returning literal arrays; `ClientMode` is an enum |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `HotReloadDataCollector::reset()` clears both properties; tagged `kernel.reset` in `profiler.yaml` for scenario A; scenario B covered by full reassignment in `collect()` |
| Request / user / locale captured in services | ✅ | `RequestStack` is injected and queried lazily; Mercure URL prefers current request server bag before `$_SERVER` |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ Info | `$_SERVER['FRANKENPHP_HOT_RELOAD']` / `$_SERVER['FRANKENPHP_MODE']` are **read** on every call (never cached or written); request bag preferred when available |
| Doctrine / EntityManager | ✅ N/A | No persistence |
| Output, headers, `exit`, shutdown functions | ✅ | None; responses are built with `Response` objects |
| Resources (files, sockets, cURL) held open | ✅ | Only one-shot `file_get_contents()`; no handles kept |
| Memory growth across requests | ✅ | No caches or accumulating arrays |
| Blocking I/O and timeouts | ⚠️ Low | Local filesystem reads per request (JS assets, Caddyfile scan in the profiler); no network I/O (see W-02) |
| Third-party static state | ✅ | Only Symfony DI/Config/HttpKernel and Twig |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` + `ruleset-hardening.neon` in `phpstan.neon.dist` |

Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` runs `php_server { hot_reload; worker { file /app/public/index.php; watch } }`, so the bundle is already exercised in worker mode.

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Nowo\HotReloadBundle\HotReloadAssets` | yes | none (`readonly` config + optional `RequestStack`) | ✅ | ✅ |
| `Nowo\HotReloadBundle\EventSubscriber\HotReloadResponseSubscriber` (`kernel.response`, -4096) | yes | none; per-request flag stored on `Request::$attributes` | ✅ | ✅ |
| `Nowo\HotReloadBundle\EventSubscriber\HotReloadAssetSubscriber` (`kernel.request`, 34) | yes | none (no properties) | ✅ | ✅ |
| `Nowo\HotReloadBundle\Diagnostics\HotReloadDiagnostics` | yes | none (`readonly` config) | ✅ | ✅ |
| `Nowo\HotReloadBundle\Twig\HotReloadTwigExtension` | yes | none | ✅ | ✅ |
| `Nowo\HotReloadBundle\DataCollector\HotReloadDataCollector` (`data_collector` + `kernel.reset`) | yes | `$data`, `$request` | ✅ (reset by Profiler / services_resetter) | ✅ (overwritten per request, see W-01) |
| `Nowo\HotReloadBundle\Command\HotReloadCheckCommand` | yes (CLI only) | none | ✅ | ✅ |

`HotReloadInjectEvent`, `HotReloadCheck`, `HotReloadDiagnosticReport` are created per call and never stored in a service. `TwigPathsPass` and `HotReloadExtension` only run at container compile time.

## Findings

### W-01 — Profiler data collector keeps the last request in properties (Mitigated)

- **Where:** `src/DataCollector/HotReloadDataCollector.php` (`$data`, `$request`), assigned in `collect()`, cleared in `lateCollect()` and `reset()`.
- **Worker impact:** under A, Profiler + `kernel.reset` clear state. Under B, `collect()` fully reassigns `$data` and `lateCollect()` sets `$request = null`. Unit tests cover two consecutive collections without calling `reset()`.
- **Recommendation:** if the collector gains new properties, clear them in `reset()` and in `lateCollect()`.

### W-02 — Filesystem reads on each request (Low)

- **Where:** `HotReloadAssetSubscriber` reads JS on every asset request; `HotReloadDiagnostics` may read the Caddyfile during profiler collect.
- **Worker impact:** local, small, bounded reads; no leak. Not caching is correct while hot reload watches files.
- **Recommendation:** none. Keep these reads uncached.

### W-03 — `$_SERVER` read at runtime (Info)

- **Where:** `HotReloadAssets::resolveMercureUrl()` (fallback after Request), `HotReloadDiagnostics` for `FRANKENPHP_MODE` / env.
- **Worker impact:** request bag is preferred for Mercure URL; process `$_SERVER` is only a CLI / no-request fallback. Values are never memoized at boot.
- **Recommendation:** keep reading lazily. Do not move env into a constructor or container parameter.

No other findings.

## Usage recommendations in worker mode

- No special bundle YAML is needed for `reset_kernel: false`. Use `worker { watch }` in the Caddyfile so workers restart when PHP files change; `nowo:hot-reload:check` already warns if `watch` is missing.
- Keep the bundle registered for `dev` only; it is not meant for production workers.
- Listeners of `HotReloadInjectEvent` must not store the `Request`/`Response` from the event in their own properties.
- If you extend or decorate `HotReloadAssets`, keep it stateless (or implement `ResetInterface`).

## Re-audit triggers

Re-run this audit when a change adds: mutable properties to `HotReloadAssets`, `HotReloadDiagnostics` or the subscribers, new properties to `HotReloadDataCollector`, caching of `FRANKENPHP_HOT_RELOAD` / file contents, or any write to superglobals.
