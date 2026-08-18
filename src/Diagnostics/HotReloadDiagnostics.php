<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Diagnostics;

use Nowo\HotReloadBundle\HotReloadAssets;
use Symfony\Component\HttpFoundation\Request;

use function file_get_contents;
use function is_file;
use function is_readable;
use function is_string;
use function preg_match;
use function sprintf;
use function trim;

/**
 * Shared environment checks for the console command and the profiler panel.
 */
final class HotReloadDiagnostics
{
    public function __construct(
        private readonly HotReloadAssets $assets,
        private readonly bool $enabled,
        private readonly bool $autoInject,
        private readonly bool $requireFrankenphpEnv,
        private readonly bool $allowProduction,
        private readonly ?string $mercureUrl,
        private readonly string $environment,
        private readonly bool $idiomorph,
        private readonly ?string $cspNonceRequestAttribute,
        private readonly bool $cspAugmentScriptSrc,
        private readonly ?string $projectDir = null,
    ) {
    }

    public function evaluate(?Request $request = null, bool $injected = false, ?string $caddyfilePath = null): HotReloadDiagnosticReport
    {
        $httpContext   = $request instanceof Request;
        $frankenphpEnv = $this->readFrankenphpEnv($request);
        $configUrl     = $this->nonEmpty($this->mercureUrl);
        $resolvedUrl   = $this->assets->resolveMercureUrl() ?? $frankenphpEnv;
        $shouldRender  = $this->computeShouldRender($resolvedUrl);

        $checks = [
            $this->checkEnabled(),
            $this->checkEnvironment(),
            $this->checkRequireFrankenphpEnv(),
            $this->checkMercureUrlConfig($configUrl),
            $this->checkFrankenphpEnv($frankenphpEnv, $httpContext, $configUrl),
            $this->checkRenderGate($shouldRender, $resolvedUrl, $httpContext),
            $this->checkAutoInject(),
            $this->checkInjected($httpContext, $injected, $shouldRender),
            $this->checkIdiomorph(),
            $this->checkCsp(),
        ];

        foreach ($this->inspectCaddyfile($caddyfilePath) as $check) {
            $checks[] = $check;
        }

        return new HotReloadDiagnosticReport(
            $checks,
            $httpContext,
            $shouldRender,
            $this->environment,
        );
    }

    private function checkEnabled(): HotReloadCheck
    {
        if ($this->enabled) {
            return new HotReloadCheck(
                'enabled',
                'Bundle enabled',
                HotReloadCheck::STATUS_PASS,
                'nowo_hot_reload.enabled is true.',
            );
        }

        return new HotReloadCheck(
            'enabled',
            'Bundle enabled',
            HotReloadCheck::STATUS_FAIL,
            'nowo_hot_reload.enabled is false — nothing will be injected.',
            'Set nowo_hot_reload.enabled: true in config/packages/dev/nowo_hot_reload.yaml.',
        );
    }

    private function checkEnvironment(): HotReloadCheck
    {
        if ($this->environment === 'prod' && !$this->allowProduction) {
            return new HotReloadCheck(
                'environment',
                'Kernel environment',
                HotReloadCheck::STATUS_FAIL,
                'kernel.environment is "prod" and allow_production is false.',
                'Register NowoHotReloadBundle for dev/test only, or set nowo_hot_reload.allow_production: true (not recommended).',
            );
        }

        if ($this->environment === 'prod') {
            return new HotReloadCheck(
                'environment',
                'Kernel environment',
                HotReloadCheck::STATUS_WARN,
                'Running in prod with allow_production: true. Hot Reload must not be used in production.',
                'Disable the bundle in prod (config/bundles.php) and keep FrankenPHP hot_reload off.',
            );
        }

        return new HotReloadCheck(
            'environment',
            'Kernel environment',
            HotReloadCheck::STATUS_PASS,
            sprintf('kernel.environment is "%s" (allow_production: %s).', $this->environment, $this->allowProduction ? 'true' : 'false'),
        );
    }

    private function checkRequireFrankenphpEnv(): HotReloadCheck
    {
        if ($this->requireFrankenphpEnv) {
            return new HotReloadCheck(
                'require_frankenphp_env',
                'Env gate',
                HotReloadCheck::STATUS_PASS,
                'require_frankenphp_env is true — inject only when mercure_url or FRANKENPHP_HOT_RELOAD is set.',
            );
        }

        return new HotReloadCheck(
            'require_frankenphp_env',
            'Env gate',
            HotReloadCheck::STATUS_WARN,
            'require_frankenphp_env is false — assets may render with an empty Mercure URL.',
            'Prefer require_frankenphp_env: true and let FrankenPHP set FRANKENPHP_HOT_RELOAD, or set mercure_url.',
        );
    }

    private function checkMercureUrlConfig(?string $configUrl): HotReloadCheck
    {
        if ($configUrl !== null) {
            return new HotReloadCheck(
                'mercure_url',
                'mercure_url config',
                HotReloadCheck::STATUS_PASS,
                sprintf('nowo_hot_reload.mercure_url is %s.', $configUrl),
            );
        }

        return new HotReloadCheck(
            'mercure_url',
            'mercure_url config',
            HotReloadCheck::STATUS_INFO,
            'nowo_hot_reload.mercure_url is null — the bundle uses FRANKENPHP_HOT_RELOAD when present.',
        );
    }

    private function checkFrankenphpEnv(?string $frankenphpEnv, bool $httpContext, ?string $configUrl): HotReloadCheck
    {
        if ($frankenphpEnv !== null) {
            return new HotReloadCheck(
                'frankenphp_hot_reload',
                'FRANKENPHP_HOT_RELOAD',
                HotReloadCheck::STATUS_PASS,
                sprintf('FRANKENPHP_HOT_RELOAD is set (%s).', $frankenphpEnv),
            );
        }

        $fix = 'Enable Mercure + php_server { hot_reload } in the Caddyfile (worker: also worker { watch }), or set nowo_hot_reload.mercure_url.';

        if ($httpContext) {
            $status = $configUrl !== null ? HotReloadCheck::STATUS_WARN : HotReloadCheck::STATUS_FAIL;
            $detail = $configUrl !== null
                ? 'FRANKENPHP_HOT_RELOAD is missing on this HTTP request; mercure_url config is used as fallback.'
                : 'FRANKENPHP_HOT_RELOAD is missing on this HTTP request. FrankenPHP only sets it when hot_reload is enabled.';

            return new HotReloadCheck(
                'frankenphp_hot_reload',
                'FRANKENPHP_HOT_RELOAD',
                $status,
                $detail,
                $fix,
            );
        }

        return new HotReloadCheck(
            'frankenphp_hot_reload',
            'FRANKENPHP_HOT_RELOAD',
            HotReloadCheck::STATUS_WARN,
            'CLI does not receive FRANKENPHP_HOT_RELOAD (FrankenPHP injects it on HTTP requests only). Open a page and check the profiler, or set mercure_url.',
            $fix,
        );
    }

    private function checkRenderGate(bool $shouldRender, ?string $resolvedUrl, bool $httpContext): HotReloadCheck
    {
        if ($shouldRender) {
            return new HotReloadCheck(
                'render_gate',
                'Render gate',
                HotReloadCheck::STATUS_PASS,
                sprintf('Gate is open. Resolved Mercure URL: %s.', $resolvedUrl ?? '(empty)'),
            );
        }

        if (!$httpContext && $this->enabled && $this->requireFrankenphpEnv && $resolvedUrl === null) {
            return new HotReloadCheck(
                'render_gate',
                'Render gate',
                HotReloadCheck::STATUS_WARN,
                'Gate looks closed from CLI because FRANKENPHP_HOT_RELOAD is not in this process. Confirm on an HTTP request via the profiler.',
                'Load an HTML page under FrankenPHP with hot_reload, or set nowo_hot_reload.mercure_url.',
            );
        }

        return new HotReloadCheck(
            'render_gate',
            'Render gate',
            HotReloadCheck::STATUS_FAIL,
            'Gate is closed — assets will not render.',
            'Set enabled: true and provide mercure_url or FRANKENPHP_HOT_RELOAD (or set require_frankenphp_env: false).',
        );
    }

    private function checkAutoInject(): HotReloadCheck
    {
        if ($this->autoInject) {
            return new HotReloadCheck(
                'auto_inject',
                'Auto-inject',
                HotReloadCheck::STATUS_PASS,
                'auto_inject is true — HTML responses get the snippet automatically.',
            );
        }

        return new HotReloadCheck(
            'auto_inject',
            'Auto-inject',
            HotReloadCheck::STATUS_WARN,
            'auto_inject is false — layouts must call {{ nowo_hot_reload_assets() }}.',
            'Set nowo_hot_reload.auto_inject: true, or add {{ nowo_hot_reload_assets() }} in the base layout <head>.',
        );
    }

    private function checkInjected(bool $httpContext, bool $injected, bool $shouldRender): HotReloadCheck
    {
        if (!$httpContext) {
            return new HotReloadCheck(
                'injected',
                'Injected this request',
                HotReloadCheck::STATUS_SKIP,
                'Injection can only be verified on an HTTP HTML response (profiler).',
            );
        }

        if ($injected) {
            return new HotReloadCheck(
                'injected',
                'Injected this request',
                HotReloadCheck::STATUS_PASS,
                'Hot Reload assets were injected into this HTML response.',
            );
        }

        if ($shouldRender && $this->autoInject) {
            return new HotReloadCheck(
                'injected',
                'Injected this request',
                HotReloadCheck::STATUS_WARN,
                'Gate is open and auto_inject is true, but this response was not injected (non-HTML, empty body, or already marked).',
                'Load a full HTML page (text/html) that does not already contain data-nowo-hot-reload.',
            );
        }

        if (!$this->autoInject) {
            return new HotReloadCheck(
                'injected',
                'Injected this request',
                HotReloadCheck::STATUS_INFO,
                'Not injected by the subscriber because auto_inject is false.',
                'Add {{ nowo_hot_reload_assets() }} to the layout or enable auto_inject.',
            );
        }

        return new HotReloadCheck(
            'injected',
            'Injected this request',
            HotReloadCheck::STATUS_INFO,
            'Not injected because the render gate is closed.',
        );
    }

    private function checkIdiomorph(): HotReloadCheck
    {
        if ($this->idiomorph) {
            return new HotReloadCheck(
                'idiomorph',
                'Idiomorph',
                HotReloadCheck::STATUS_PASS,
                'Idiomorph is on — the client morphs the DOM instead of a full reload.',
            );
        }

        return new HotReloadCheck(
            'idiomorph',
            'Idiomorph',
            HotReloadCheck::STATUS_INFO,
            'Idiomorph is off — the client will fully reload the page.',
        );
    }

    private function checkCsp(): HotReloadCheck
    {
        $nonce = $this->nonEmpty($this->cspNonceRequestAttribute);
        if ($nonce !== null) {
            return new HotReloadCheck(
                'csp',
                'CSP',
                HotReloadCheck::STATUS_PASS,
                sprintf(
                    'csp_nonce_request_attribute is "%s"; csp_augment_script_src is %s.',
                    $nonce,
                    $this->cspAugmentScriptSrc ? 'true' : 'false',
                ),
            );
        }

        if ($this->cspAugmentScriptSrc) {
            return new HotReloadCheck(
                'csp',
                'CSP',
                HotReloadCheck::STATUS_INFO,
                'No CSP nonce attribute configured; csp_augment_script_src will append CDN hosts to an existing script-src.',
            );
        }

        return new HotReloadCheck(
            'csp',
            'CSP',
            HotReloadCheck::STATUS_WARN,
            'No CSP nonce and csp_augment_script_src is false — a strict CSP may block jsDelivr scripts.',
            'Set csp_nonce_request_attribute and/or csp_augment_script_src: true, or self-host the scripts (see docs/CSP.md).',
        );
    }

    /**
     * @return list<HotReloadCheck>
     */
    private function inspectCaddyfile(?string $caddyfilePath): array
    {
        $path = $caddyfilePath ?? $this->detectCaddyfile();
        if ($path === null) {
            return [
                new HotReloadCheck(
                    'caddyfile',
                    'Caddyfile',
                    HotReloadCheck::STATUS_INFO,
                    'No Caddyfile auto-detected. Pass --caddyfile=PATH on the command to scan mercure / hot_reload.',
                    'Caddy needs mercure { anonymous } and php_server { hot_reload } (worker: also worker { watch }).',
                ),
            ];
        }

        if (!is_file($path) || !is_readable($path)) {
            return [
                new HotReloadCheck(
                    'caddyfile',
                    'Caddyfile',
                    HotReloadCheck::STATUS_FAIL,
                    sprintf('Caddyfile is not readable: %s', $path),
                    'Pass a readable Caddyfile path with --caddyfile=PATH.',
                ),
            ];
        }

        $contents = (string) file_get_contents($path);

        $checks = [
            new HotReloadCheck(
                'caddyfile',
                'Caddyfile',
                HotReloadCheck::STATUS_PASS,
                sprintf('Scanning %s', $path),
            ),
        ];

        $hasMercure = preg_match('/\bmercure\b/', $contents) === 1;
        $checks[]   = $hasMercure
            ? new HotReloadCheck(
                'caddy_mercure',
                'Caddy mercure',
                HotReloadCheck::STATUS_PASS,
                'Caddyfile contains a mercure directive.',
            )
            : new HotReloadCheck(
                'caddy_mercure',
                'Caddy mercure',
                HotReloadCheck::STATUS_FAIL,
                'Caddyfile has no mercure block.',
                'Add mercure { anonymous } (see docs/USAGE.md).',
            );

        $hasHotReload = preg_match('/\bhot_reload\b/', $contents) === 1;
        $checks[]     = $hasHotReload
            ? new HotReloadCheck(
                'caddy_hot_reload',
                'Caddy hot_reload',
                HotReloadCheck::STATUS_PASS,
                'Caddyfile contains php_server { hot_reload } (or hot_reload).',
            )
            : new HotReloadCheck(
                'caddy_hot_reload',
                'Caddy hot_reload',
                HotReloadCheck::STATUS_FAIL,
                'Caddyfile has no hot_reload directive — FrankenPHP will not set FRANKENPHP_HOT_RELOAD.',
                'Add hot_reload inside php_server { } (see docs/USAGE.md).',
            );

        $hasWorker = preg_match('/\bworker\s*\{/', $contents) === 1;
        if ($hasWorker) {
            $hasWatch = preg_match('/\bwatch\b/', $contents) === 1;
            $checks[] = $hasWatch
                ? new HotReloadCheck(
                    'caddy_worker_watch',
                    'Caddy worker watch',
                    HotReloadCheck::STATUS_PASS,
                    'Worker block includes watch.',
                )
                : new HotReloadCheck(
                    'caddy_worker_watch',
                    'Caddy worker watch',
                    HotReloadCheck::STATUS_WARN,
                    'Worker block found without watch — file changes may not trigger reloads.',
                    'Add watch inside worker { file …; watch } (see docs/USAGE.md).',
                );
        } else {
            $checks[] = new HotReloadCheck(
                'caddy_worker_watch',
                'Caddy worker watch',
                HotReloadCheck::STATUS_INFO,
                'No worker { } block — classic php_server mode (watch is not required).',
            );
        }

        return $checks;
    }

    private function detectCaddyfile(): ?string
    {
        $root = $this->nonEmpty($this->projectDir);
        if ($root === null) {
            return null;
        }

        $mode       = $this->readFrankenphpMode();
        $candidates = [];
        if ($mode === 'classic') {
            $candidates[] = $root . '/docker/frankenphp/Caddyfile.dev';
        }
        $candidates[] = $root . '/Caddyfile';
        $candidates[] = $root . '/docker/frankenphp/Caddyfile';
        $candidates[] = $root . '/docker/frankenphp/Caddyfile.dev';
        $candidates[] = $root . '/frankenphp/Caddyfile';

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function readFrankenphpMode(): ?string
    {
        $value = $_SERVER['FRANKENPHP_MODE'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function readFrankenphpEnv(?Request $request): ?string
    {
        if ($request instanceof Request) {
            $fromRequest = $this->nonEmpty($request->server->get('FRANKENPHP_HOT_RELOAD'));
            if ($fromRequest !== null) {
                return $fromRequest;
            }
        }

        $fromServer = $_SERVER['FRANKENPHP_HOT_RELOAD'] ?? null;

        return $this->nonEmpty($fromServer);
    }

    private function computeShouldRender(?string $resolvedUrl): bool
    {
        if (!$this->enabled) {
            return false;
        }
        if ($resolvedUrl !== null) {
            return true;
        }

        return !$this->requireFrankenphpEnv;
    }

    private function nonEmpty(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
