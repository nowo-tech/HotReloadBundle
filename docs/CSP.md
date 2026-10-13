# Content Security Policy (CSP)

Hot Reload injects **third-party** (or self-hosted) scripts into HTML in **development only**. Strict CSP policies (`script-src 'self'` without CDNs, and nonce-only inline scripts) need a small host adjustment.

## What is injected

When the render gate is open:

1. `<meta name="frankenphp-hot-reload:url" …>`
2. Idiomorph classic `<script src="…">` (optional)
3. frankenphp-hot-reload ESM `<script type="module" src="…">`
4. Optional **inline** preserve boot script (marks Web Debug Toolbar nodes)

Default script URLs are **version-pinned** on jsDelivr:

- `https://cdn.jsdelivr.net/npm/idiomorph@0.7.4`
- `https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm`

## Recommended host setup

### 1. CSP nonce for the preserve boot script

Set the request attribute that already holds your page nonce:

```yaml
# config/packages/dev/nowo_hot_reload.yaml
nowo_hot_reload:
    csp_nonce_request_attribute: '_csp_nonce'   # or your host attribute name
```

The bundle reads that attribute via `RequestStack` and emits `nonce="…"` on **every** `<script>` it injects (Idiomorph, the CDN module or bundle client, the JSON config block and the inline preserve script), so nonce-based (`'strict-dynamic'`) policies work too.

### 2. Allow the CDN (or self-host)

**Option A — auto-augment (default):** when the response already has a `Content-Security-Policy` (or `Content-Security-Policy-Report-Only`) header, `HotReloadResponseSubscriber` appends the configured hosts to every `script-src` and `script-src-elem` directive. When the policy has no `script-src` but restricts scripts via `default-src`, a `script-src` is added seeded with the `default-src` sources plus the hosts (e.g. `default-src 'self'` → `script-src 'self' https://cdn.jsdelivr.net`), so same-origin scripts keep working. In bundle client modes (`visibility`, `shared_worker`, `always`) `'self'` is also added when the directive does not already allow the request origin (e.g. `default-src 'none'` → `script-src https://cdn.jsdelivr.net 'self'`), because `/_nowo/hot-reload/client.js` is served same-origin. Policies that do not restrict scripts at all are left untouched:

```yaml
nowo_hot_reload:
    csp_augment_script_src: true
    csp_script_src_hosts:
        - 'https://cdn.jsdelivr.net'
```

**Option B — host CSP:** in `kernel.debug` / `dev`, add `https://cdn.jsdelivr.net` to `script-src` yourself.

**Option C — self-host:** point URLs at first-party assets so `script-src 'self'` is enough:

```yaml
nowo_hot_reload:
    idiomorph_script_url: '/build/idiomorph.js'
    hot_reload_script_url: '/build/frankenphp-hot-reload.js'
    csp_augment_script_src: false
```

## Event hook

Listen to `Nowo\HotReloadBundle\Event\HotReloadInjectEvent` to mutate the snippet or the response headers right before injection. Calling `setSnippet('')` cancels injection for that response.

Auto-inject already skips `/_wdt` and `/_profiler` via `ignore_path_prefixes` (see [CONFIGURATION.md](CONFIGURATION.md)).

## Production

Do **not** enable this bundle or FrankenPHP `hot_reload` in production. The Flex recipe registers the bundle for `dev` / `test` only. `allow_production: false` (default) rejects `enabled: true` when `kernel.environment` is `prod`.

See [SECURITY.md](SECURITY.md) and [CONFIGURATION.md](CONFIGURATION.md).
