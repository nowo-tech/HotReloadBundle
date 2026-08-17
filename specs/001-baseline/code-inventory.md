# Code inventory — Hot Reload Bundle (`src/`)

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/hot-reload-bundle`  
**Last audited**: 2026-08-17

100% inventory of production PHP and shipped config under `src/`. Every file maps to at least one FR-* in the baseline product spec.

**Total production sources under `src/`:** 12 (matches `find src -type f | wc -l`).

## Bundle entry

| File | Responsibility | Spec |
| --- | --- | --- |
| `../NowoHotReloadBundle.php` | Bundle entry; returns `HotReloadExtension`; registers `TwigPathsPass` | FR-01 |

## Core

| File | Responsibility | Spec |
| --- | --- | --- |
| `HotReloadAssets.php` | Render gate, Mercure URL resolve, HTML snippet, preserve boot script; marker `data-nowo-hot-reload` | FR-02, FR-03, FR-04 |

## HTTP / events

| File | Responsibility | Spec |
| --- | --- | --- |
| `EventSubscriber/HotReloadResponseSubscriber.php` | Auto-inject into HTML responses before `</head>` / `</body>` | FR-05, FR-07 |
| `Event/HotReloadInjectEvent.php` | Dispatched before HTML injection so hosts can mutate snippet/headers | FR-05 |

## Data collector

| File | Responsibility | Spec |
| --- | --- | --- |
| `DataCollector/HotReloadDataCollector.php` | Web Debug Toolbar / Profiler panel (`nowo_hot_reload`) | FR-01 |

## Twig

| File | Responsibility | Spec |
| --- | --- | --- |
| `Twig/HotReloadTwigExtension.php` | Registers `nowo_hot_reload_assets` Twig function | FR-06 |

## Dependency injection

| File | Responsibility | Spec |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Config tree `nowo_hot_reload` | FR-01 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Registers Twig namespace `NowoHotReloadBundle` (`addPath` / `prependPath`) | FR-01 |
| `DependencyInjection/HotReloadExtension.php` | Parameters + load `services.yaml` / `twig.yaml` | FR-01 |

## Resources (non-PHP)

| File | Responsibility | Spec |
| --- | --- | --- |
| `Resources/config/services.yaml` | `HotReloadAssets` + `HotReloadResponseSubscriber` wiring | FR-02 … FR-05, FR-07 |
| `Resources/config/twig.yaml` | Twig extension service | FR-06 |
| `Resources/config/profiler.yaml` | Data collector + `@NowoHotReloadBundle` profiler template | FR-01 |
| `Resources/config/packages/nowo_hot_reload.yaml` | Sample / default package config | FR-01 |
