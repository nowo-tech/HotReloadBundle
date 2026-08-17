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

The bundle reads that attribute via `RequestStack` and emits `nonce="…"` on the inline preserve `<script>`.

### 2. Allow the CDN (or self-host)

**Option A — auto-augment (default):** when the response already has a `Content-Security-Policy` header, `HotReloadResponseSubscriber` appends configured hosts to `script-src` (or adds a `script-src` directive):

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

Listen to `Nowo\HotReloadBundle\Event\HotReloadInjectEvent` to mutate the snippet or the response headers right before injection.

## Production

Do **not** enable this bundle or FrankenPHP `hot_reload` in production. The Flex recipe registers the bundle for `dev` / `test` only. `allow_production: false` (default) rejects `enabled: true` when `kernel.environment` is `prod`.

See [SECURITY.md](SECURITY.md) and [CONFIGURATION.md](CONFIGURATION.md).
