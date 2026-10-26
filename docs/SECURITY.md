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
- **Bundle client assets (v1.5.0+)** — two same-origin static routes, `/_nowo/hot-reload/client.js` and `/_nowo/hot-reload/shared-worker.js`, answered on `kernel.request` (priority 34, before the router and the firewall) from a fixed two-entry map: no request input reaches the filesystem, no mutations, and the files are the public bundle JavaScript (no configuration or secrets). They are unauthenticated by design.
- **Client modes** — `shared_worker` (one EventSource for all tabs), `visibility` (closes while hidden) and `always` (one connection per tab); the browser connects to the Mercure hub, the bundle hosts no streaming endpoint.
- **CSP augmentation** — with `csp_augment_script_src: true` (default) dev responses get `'self'` and `https://cdn.jsdelivr.net` added to `script-src` (`'none'` is removed). See [CSP.md](CSP.md).
- **Server side** (outside this package) — Mercure `anonymous` and `php_server { hot_reload }` in the Caddyfile. Setup: [ENVIRONMENT.md](ENVIRONMENT.md).

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
| **Date** | 2026-10-26 (re-audit of v1.6.2; previous: 2026-08-17, Pass (conditional)) |
| **Method** | Claude Code security review (static, `src/` + Flex recipe + client assets + docs) |
| **Grade** | **Pass (good)** |
| **Overall residual risk** | **Low** |

### Residuals (accepted)

- Integrator enabling the bundle or Caddy `hot_reload` in production — **application-owned**. The prod guard (`allow_production: false`) only applies to the environment named `prod`: do not register the bundle in other production-like environments (`staging`, `production`, …).
- The two `/_nowo/hot-reload/*.js` asset routes are served regardless of `enabled` / `client_mode` once the bundle is registered (public bundle code only).
- CSP auto-augment widens `script-src` with `'self'` and `https://cdn.jsdelivr.net` on dev responses; set `csp_augment_script_src: false` or self-host the scripts for strict policies.
- Loading third-party CDN scripts when Idiomorph / hot-reload URLs are left at defaults (no Subresource Integrity) — residual Medium for strict CSP environments; mitigate by self-hosting URLs.

No Critical/High findings remain open for shipping.
