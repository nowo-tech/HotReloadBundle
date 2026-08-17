# Hot Reload Bundle — Baseline product specification

**Package**: `nowo-tech/hot-reload-bundle`  
**Last audited**: 2026-08-17  
**Inventory**: [`code-inventory.md`](code-inventory.md)

## Overview

HotReloadBundle injects [FrankenPHP Hot Reload](https://frankenphp.dev/docs/hot-reload/) client assets (meta Mercure URL, optional Idiomorph, frankenphp-hot-reload ESM) into Symfony HTML responses in development. Auto-inject via `HotReloadResponseSubscriber` or Twig `{{ nowo_hot_reload_assets() }}`.

## Functional requirements

| ID | Requirement |
| --- | --- |
| FR-01 | Config alias is **`nowo_hot_reload`** with keys: `enabled`, `auto_inject`, `require_frankenphp_env`, `mercure_url`, `idiomorph`, `idiomorph_script_url`, `hot_reload_script_url`, `preserve_selectors`. |
| FR-02 | **`HotReloadAssets::shouldRender()`** is true only when `enabled` and (`mercure_url` or `FRANKENPHP_HOT_RELOAD` is non-empty, or `require_frankenphp_env` is false). |
| FR-03 | **`renderHtml()`** emits meta `frankenphp-hot-reload:url`, optional Idiomorph script, frankenphp-hot-reload `type="module"`, and optional preserve boot script; all marked with **`data-nowo-hot-reload`**. |
| FR-04 | Preserve selectors (default `#sfwdt`, `.sf-toolbar`) receive **`data-frankenphp-hot-reload-preserve`** via the boot script. |
| FR-05 | With **`auto_inject: true`**, `HotReloadResponseSubscriber` injects the snippet before `</head>`, else `</body>`, else appends; only HTML main responses; skips if marker already present. |
| FR-06 | Twig exposes **`{{ nowo_hot_reload_assets() }}`** (HTML-safe) returning the same snippet or empty when the gate fails. |
| FR-07 | Non-HTML responses are never modified by the subscriber. |

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
**Then** the preserve boot script marks `#sfwdt` / `.sf-toolbar` with `data-frankenphp-hot-reload-preserve`.

### US-05 — Idempotent injection

**Given** the layout already includes assets with `data-nowo-hot-reload`  
**When** the response subscriber runs  
**Then** it does not inject a second copy.

## Out of scope

- Configuring FrankenPHP / Caddy Mercure / `hot_reload` / worker `watch` (integrator + server docs).
- Enabling Hot Reload in production.
- Injecting into JSON/API responses.

## Traceability

- FR-01: `tests/Unit/DependencyInjection/ConfigurationTest.php`, `tests/Integration/DependencyInjection/HotReloadExtensionIntegrationTest.php`
- FR-02 … FR-04: `tests/Unit/HotReloadAssetsTest.php`
- FR-05, FR-07: `tests/Unit/EventSubscriber/HotReloadResponseSubscriberTest.php`
- FR-06: `tests/Unit/Twig/HotReloadTwigExtensionTest.php`
