# Code inventory — Hot Reload Bundle (`src/`)

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/hot-reload-bundle`  
**Last audited**: 2026-11-24

100% inventory of production PHP and shipped config / public assets under `src/`. Every file maps to at least one FR-* in the baseline product spec.

**Total production sources under `src/`:** 23 (matches `find src -type f | wc -l`).

## Bundle entry

| File | Responsibility | Spec |
| --- | --- | --- |
| `../NowoHotReloadBundle.php` | Bundle entry; returns `HotReloadExtension`; registers `TwigPathsPass` | FR-01 |

## Core

| File | Responsibility | Spec |
| --- | --- | --- |
| `HotReloadAssets.php` | Render gate, Mercure URL resolve (Request then `$_SERVER`), request-attribute CSP nonce (`resolveRequestNonce()`), HTML snippet (CDN or bundle client), preserve boot script; marker `data-nowo-hot-reload` | FR-02, FR-03, FR-04, FR-10, FR-11 |

## Client modes

| File | Responsibility | Spec |
| --- | --- | --- |
| `Client/ClientMode.php` | Enum / labels for `cdn`, `visibility`, `shared_worker`, `always` | FR-01, FR-10 |
| `Client/ClientModeGuide.php` | Profiler Help catalog and compare-table rows for multi-tab approaches | FR-09, FR-10 |

## HTTP / events

| File | Responsibility | Spec |
| --- | --- | --- |
| `EventSubscriber/HotReloadResponseSubscriber.php` | Auto-inject into HTML responses before `</head>` / `</body>`; reuses the script nonce already in the response CSP when no request-attribute nonce resolves | FR-05, FR-07 |
| `EventSubscriber/HotReloadAssetSubscriber.php` | Serves `/_nowo/hot-reload/client.js` and `shared-worker.js` | FR-10 |
| `Event/HotReloadInjectEvent.php` | Dispatched before HTML injection so hosts can mutate snippet/body; scalar accessors (`getPath()`, `getContentType()`, `getBody()`/`setBody()`, `getSnippet()`/`setSnippet()`) and `fromContent()` since 1.7.0 | FR-05, FR-12 |
| `Event/HotReloadInjectEventState.php` | Keeps the request, response and body of each inject event in a `WeakMap` outside the event's properties (no leak through a dump, no state in a worker) | FR-05, FR-11, FR-12 |

## Diagnostics

| File | Responsibility | Spec |
| --- | --- | --- |
| `Diagnostics/HotReloadCheck.php` | One pass/fail/warn/info/skip check | FR-08, FR-09 |
| `Diagnostics/HotReloadDiagnosticReport.php` | Aggregated check report | FR-08, FR-09 |
| `Diagnostics/HotReloadDiagnostics.php` | Shared evaluator for CLI + profiler (env, Caddyfile, render gate, client_mode / protocol) | FR-08, FR-09, FR-10 |

## Commands

| File | Responsibility | Spec |
| --- | --- | --- |
| `Command/HotReloadCheckCommand.php` | `nowo:hot-reload:check` (`--caddyfile`, `--json`, `--strict`) | FR-08 |

## Data collector

| File | Responsibility | Spec |
| --- | --- | --- |
| `DataCollector/HotReloadDataCollector.php` | Web Debug Toolbar / Profiler panel (`nowo_hot_reload`); `ResetInterface` + `kernel.reset` for worker / `reset_kernel: false` | FR-01, FR-09, FR-10, FR-11 |

## Twig

| File | Responsibility | Spec |
| --- | --- | --- |
| `Twig/HotReloadTwigExtension.php` | Registers `nowo_hot_reload_assets` Twig function | FR-06 |

## Dependency injection

| File | Responsibility | Spec |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Config tree `nowo_hot_reload` | FR-01 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Registers Twig namespace `NowoHotReloadBundle` (`addPath` / `prependPath`) | FR-01 |
| `DependencyInjection/HotReloadExtension.php` | Parameters + load `services.yaml` / `twig.yaml` / `profiler.yaml` / `commands.yaml` (when Console / Profiler / Twig exist) | FR-01, FR-08, FR-09 |

## Resources (non-PHP)

| File | Responsibility | Spec |
| --- | --- | --- |
| `Resources/config/services.yaml` | `HotReloadAssets` + subscribers + `HotReloadDiagnostics` wiring | FR-02 … FR-05, FR-07, FR-08, FR-10 |
| `Resources/config/twig.yaml` | Twig extension service | FR-06 |
| `Resources/config/profiler.yaml` | Data collector + `@NowoHotReloadBundle` profiler template + `kernel.reset` | FR-01, FR-09, FR-11 |
| `Resources/config/commands.yaml` | `nowo:hot-reload:check` console command | FR-08 |
| `Resources/config/packages/nowo_hot_reload.yaml` | Sample / default package config | FR-01 |
| `Resources/public/hot-reload-client.js` | Bundle Mercure client for `visibility` / `shared_worker` / `always` | FR-10 |
| `Resources/public/hot-reload-shared-worker.js` | SharedWorker SSE fan-out for `shared_worker` mode | FR-10 |

## Templates (outside `src/`, shipped)

| File | Responsibility | Spec |
| --- | --- | --- |
| `templates/Collector/hot_reload.html.twig` | Profiler panel (`sf-tabs`: Environment checks, Runtime, Client assets, CSP, Help); no inline `style=` (nonced `<style>` class) | FR-09 |
| `templates/Icon/hot-reload.svg` | Toolbar / menu icon | FR-09 |
