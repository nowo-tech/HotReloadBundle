# Performance

This bundle adds Hot Reload client assets only when the render gate is open. There is no background worker, no database, and no I/O beyond the HTML response you already send.

## Table of contents

- [Short answer](#short-answer)
- [What runs on each request](#what-runs-on-each-request)
  - [1. Kernel response subscriber](#1-kernel-response-subscriber)
  - [2. Gate closed / auto_inject off](#2-gate-closed--auto_inject-off)
  - [3. Gate open — HTML injection](#3-gate-open--html-injection)
- [Twig helper](#twig-helper)
- [Recommendations](#recommendations)
- [Configuration levers](#configuration-levers)
- [Summary](#summary)

## Short answer

| Scenario | Server impact |
| --- | --- |
| `enabled: false`, or no Mercure URL / env with `require_frankenphp_env: true` | **Zero body work** — subscriber exits before reading the response |
| `auto_inject: false` | **Zero** from the subscriber; Twig pays only if you call `nowo_hot_reload_assets()` |
| Gate open + HTML main response | **One HTML rewrite** — read body, insert a small snippet before `</head>` / `</body>`, write back |
| Non-HTML response | **Zero** injection (subscriber skips) |

## What runs on each request

### 1. Kernel response subscriber

`HotReloadResponseSubscriber` is registered at priority `-4096` on `kernel.response`. On every main request it checks `auto_inject` and `HotReloadAssets::shouldRender()` first.

### 2. Gate closed / auto_inject off

When injection must not run:

- A few boolean / null checks
- **No** `getContent()`, **no** regex replace, **no** `setContent()`

Cost: negligible (microseconds).

### 3. Gate open — HTML injection

Only when **all** of the following are true:

- `auto_inject` is true
- `shouldRender()` is true
- Main request
- Content-Type is HTML / XHTML
- Body is non-empty
- Marker `data-nowo-hot-reload` not already present

Then the subscriber:

1. Reads the **full response body**
2. Builds the meta + script snippet (`HotReloadAssets::renderHtml()`)
3. Inserts before `</head>` or `</body>` (or appends)
4. Writes the modified body back

Effects:

- Extra CPU proportional to response size (one string replace)
- Slightly larger HTML download (meta + 1–3 small scripts)
- Slightly higher memory peak (body string duplication during replace)

## Twig helper

`{{ nowo_hot_reload_assets() }}` calls the same `renderHtml()` path. With `auto_inject: true`, prefer not duplicating the helper in layouts (the subscriber skips if the marker is already present).

## Recommendations

1. Register the bundle for **`dev` / `test` only** — production should never load these assets.
2. Keep default **`require_frankenphp_env: true`** so non-FrankenPHP PHP-FPM/CLI processes do no injection.
3. Prefer **`auto_inject: true`** unless your layout needs an explicit placement.
4. Self-host CDN URLs only if CSP or offline constraints require it — same injection cost either way.

## Configuration levers

| Option | Effect on cost |
| --- | --- |
| `enabled: false` | No render; subscriber no-ops after gate check |
| `require_frankenphp_env: true` (default) | No body rewrite without Mercure URL / env |
| `auto_inject: false` | No subscriber rewrite; opt-in via Twig |
| `idiomorph: false` | Slightly smaller snippet (one fewer script tag) |
| `preserve_selectors: []` | Omits the preserve boot script |

## Summary

- **Zero** HTML mutation when the gate is closed or `auto_inject` is off.
- **One** HTML rewrite when FrankenPHP Hot Reload is active and auto-inject is on — the intended cost of the feature.
