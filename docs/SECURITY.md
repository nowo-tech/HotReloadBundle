# Security

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threats and mitigations](#threats-and-mitigations)
- [Logging and observability (REQ-OBS-001)](#logging-and-observability-req-obs-001)
- [Dependencies](#dependencies)
- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Supported versions](#supported-versions)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)
- [AI security audit (REQ-SEC-004)](#ai-security-audit-req-sec-004)
  - [Residuals (accepted)](#residuals-accepted)

## Scope

HotReloadBundle injects FrankenPHP Hot Reload **client** scripts and a Mercure hub meta tag into **HTML responses** when the configured render gate is open. It is a **development** aid: FrankenPHP `hot_reload` and this bundle must **not** be enabled in production.

## Attack surface

- **Injected scripts** — Idiomorph (optional), frankenphp-hot-reload ESM, and a small preserve-selector helper, marked with `data-nowo-hot-reload`.
- **Meta tag** — `frankenphp-hot-reload:url` with the Mercure hub URL from config or `FRANKENPHP_HOT_RELOAD`.
- **Configuration** (`nowo_hot_reload.*`) and CDN script URLs in the host application.
- **Server side** (outside this package) — Mercure `anonymous` and `php_server { hot_reload }` in the Caddyfile.

## Threats and mitigations

| Threat | Mitigation |
|--------|------------|
| Hot reload left on in production | Documented as **dev-only**; Flex recipe registers `dev`/`test` only; `allow_production: false` rejects `enabled` in `prod`; never enable Caddy `hot_reload` in production. |
| Anonymous Mercure exposure | Expected for local Hot Reload; do not expose that Mercure setup on public production hosts. |
| XSS via injected URLs | Mercure and script URLs are escaped with `htmlspecialchars` (`ENT_QUOTES \| ENT_SUBSTITUTE`). Prefer trusted CDN defaults or first-party URLs. |
| Accidental injection without FrankenPHP | Default `require_frankenphp_env: true` skips rendering when neither `mercure_url` nor `FRANKENPHP_HOT_RELOAD` is set. |
| CSP blocking CDN / inline preserve script | See [CSP.md](CSP.md): `csp_nonce_request_attribute`, `csp_augment_script_src`, version-pinned CDN defaults, or self-host. |

## Logging and observability (REQ-OBS-001)

This bundle **does not** inject `Psr\Log\LoggerInterface` / Monolog. There are no network clients, destructive CLI commands, or filesystem writes that require operational start/success/failure logs.

- **Observability surface:** browser Hot Reload client connected to Mercure when the gate is open.
- Host applications may log misconfiguration themselves; the bundle ships no dedicated Monolog channel.
- Shipped `src/` must not use `dump()`, `error_log()`, or `var_dump()` for production paths (enforced in review / PHPStan hygiene).

## Dependencies

Run `composer audit`; keep Symfony components and this bundle updated. CDN scripts (Idiomorph, frankenphp-hot-reload) are loaded by the browser when enabled — defaults are **version-pinned**; pin further or self-host via `idiomorph_script_url` / `hot_reload_script_url` if required by your security policy. See [CSP.md](CSP.md).

## Reporting a vulnerability

Report via [GitHub Security Advisories](https://github.com/nowo-tech/HotReloadBundle/security/advisories) or the issue tracker. Avoid public disclosure until addressed.

## Supported versions

Security fixes apply to the current major release line. Upgrade to the latest tag.

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | Current; linked from README. |
| **`.gitignore` and `.env`** | No committed secrets. |
| **Recipe / Flex** | Defaults keep `require_frankenphp_env: true`; docs say register for `dev` only. |
| **Input / output** | URL escaping for meta/script attributes documented. |
| **Dependencies** | `composer audit` triaged. |
| **Logging** | No Monolog channel (see above). |
| **Permissions / exposure** | Document that `hot_reload` must not be enabled in production. |

Record confirmation in the release PR or tag notes.

## AI security audit (REQ-SEC-004)

| Field | Value |
|-------|--------|
| **Date** | 2026-08-17 |
| **Method** | Campaign static review of `src/`, package config, demos, and this document |
| **Grade** | **Pass (conditional)** |
| **Overall residual risk** | **Low** |

### Residuals (accepted)

- Integrator enabling the bundle or Caddy `hot_reload` in production — **application-owned**.
- Loading third-party CDN scripts when Idiomorph / hot-reload URLs are left at defaults — residual Medium for strict CSP environments; mitigate by self-hosting URLs.

No Critical/High findings remain open for shipping.
