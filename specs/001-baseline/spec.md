# Hot Reload Bundle — Baseline product specification

**Package**: `nowo-tech/hot-reload-bundle`  
**Last audited**: 2026-08-18  
**Inventory**: [`code-inventory.md`](code-inventory.md)

## Overview

HotReloadBundle injects [FrankenPHP Hot Reload](https://frankenphp.dev/docs/hot-reload/) client assets (meta Mercure URL, optional Idiomorph, frankenphp-hot-reload ESM) into Symfony HTML responses in development. Auto-inject via `HotReloadResponseSubscriber` or Twig `{{ nowo_hot_reload_assets() }}`. Supported runtimes: PHP `>=8.1 <8.6`, Symfony **7.4+** and **8.0–8.2**.

## Functional requirements

| ID | Requirement |
| --- | --- |
| FR-01 | Config alias is **`nowo_hot_reload`** with keys: `enabled`, `auto_inject`, `require_frankenphp_env`, `allow_production`, `mercure_url`, `idiomorph`, `idiomorph_script_url`, `hot_reload_script_url`, `preserve_selectors`, `preserve_observe`, `csp_nonce_request_attribute`, `csp_augment_script_src`, `csp_script_src_hosts`. |
| FR-02 | **`HotReloadAssets::shouldRender()`** is true only when `enabled` and (`mercure_url` or `FRANKENPHP_HOT_RELOAD` is non-empty, or `require_frankenphp_env` is false). |
| FR-03 | **`renderHtml()`** emits meta `frankenphp-hot-reload:url`, optional Idiomorph script, frankenphp-hot-reload `type="module"`, and optional preserve boot script; all marked with **`data-nowo-hot-reload`**. |
| FR-04 | Preserve selectors (default `[id^="sfwdt"]`, `.sf-toolbar`, `.sf-minitoolbar`) receive **`data-frankenphp-hot-reload-preserve`** via the boot script. |
| FR-05 | With **`auto_inject: true`**, `HotReloadResponseSubscriber` injects the snippet before `</head>`, else `</body>`, else appends; only HTML main responses; skips if marker already present. |
| FR-06 | Twig exposes **`{{ nowo_hot_reload_assets() }}`** (HTML-safe) returning the same snippet or empty when the gate fails. |
| FR-07 | Non-HTML responses are never modified by the subscriber. |
| FR-08 | **`nowo:hot-reload:check`** reports pass/fail/warn for enabled, kernel environment, env gate, `FRANKENPHP_HOT_RELOAD`, `mercure_url`, render gate, auto-inject, optional Caddyfile (`mercure`, `hot_reload`, worker `watch`), and prints fixes for failures/warnings. |
| FR-09 | **`HotReloadDataCollector`** stores the same diagnostic checks (serializable). The profiler panel lists status, detail, and what to do. The toolbar truncates long Mercure URLs (`/.well-known/mercure?...`) with the full value in the hover `title`. |

## User scenarios

### US-01 — Auto-inject under FrankenPHP Hot Reload

**Given** `enabled` and `auto_inject` are true and `FRANKENPHP_HOT_RELOAD` is set  
**When** the app returns an HTML page  
**Then** the response contains meta + scripts with `data-nowo-hot-reload` before `</head>` (or `</body>`).

### US-02 — Gate closed without env

**Given** `require_frankenphp_env: true` and no `mercure_url` / `FRANKENPHP_HOT_RELOAD`  
**When** any HTML response is sent  
**Then** no Hot Reload assets are injected.

### US-03 — Manual Twig inject

**Given** `auto_inject: false` and the layout calls `{{ nowo_hot_reload_assets() }}` with the gate open  
**When** the page renders  
**Then** the same asset HTML appears where the helper was placed.

### US-04 — Preserve Web Debug Toolbar

**Given** default `preserve_selectors`  
**When** assets render  
**Then** the preserve boot script marks `[id^="sfwdt"]` / `.sf-toolbar` / `.sf-minitoolbar` with `data-frankenphp-hot-reload-preserve`.

### US-05 — Idempotent injection

**Given** the layout already includes assets with `data-nowo-hot-reload`  
**When** the response subscriber runs  
**Then** it does not inject a second copy.

### US-06 — Diagnose the stack from CLI

**Given** the bundle is installed and Symfony Console is available  
**When** the integrator runs `php bin/console nowo:hot-reload:check` (optionally `--caddyfile`, `--json`, `--strict`)  
**Then** each environment check is reported as pass/fail/warn/info/skip with a fix line when something is missing. CLI cannot see HTTP-only `FRANKENPHP_HOT_RELOAD`; that row may warn until confirmed in the profiler.

### US-07 — See environment checks in the profiler

**Given** `kernel.debug` is true and the Web Profiler is enabled  
**When** the integrator opens an HTML page  
**Then** the Hot Reload toolbar/panel shows the same diagnostic checklist as the CLI, plus runtime injection status.

## Out of scope

- Writing FrankenPHP / Caddy Mercure / `hot_reload` / worker `watch` configuration (integrator-owned). The check command may **inspect** a Caddyfile when `--caddyfile` is given; it does not generate or modify one.
- Enabling Hot Reload in production.
- Injecting into JSON/API responses.

## Traceability

- FR-01: `tests/Unit/DependencyInjection/ConfigurationTest.php`, `tests/Integration/DependencyInjection/HotReloadExtensionIntegrationTest.php`
- FR-02 … FR-04: `tests/Unit/HotReloadAssetsTest.php`
- FR-05, FR-07: `tests/Unit/EventSubscriber/HotReloadResponseSubscriberTest.php`
- FR-06: `tests/Unit/Twig/HotReloadTwigExtensionTest.php`
- FR-08: `tests/Unit/Command/HotReloadCheckCommandTest.php`, `tests/Unit/Diagnostics/HotReloadDiagnosticsTest.php`
- FR-09: `tests/Unit/DataCollector/HotReloadDataCollectorTest.php`
