<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\Diagnostics;

use Nowo\HotReloadBundle\Diagnostics\HotReloadCheck;
use Nowo\HotReloadBundle\Diagnostics\HotReloadDiagnostics;
use Nowo\HotReloadBundle\HotReloadAssets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class HotReloadDiagnosticsTest extends TestCase
{
    private ?string $tempDir = null;

    protected function tearDown(): void
    {
        unset($_SERVER['FRANKENPHP_HOT_RELOAD'], $_SERVER['FRANKENPHP_MODE']);
        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
        }
        parent::tearDown();
    }

    #[Test]
    public function itWarnsOnCliWhenFrankenphpEnvIsMissing(): void
    {
        $report = $this->createDiagnostics()->evaluate();
        $byId   = $this->index($report->getChecks());

        self::assertFalse($report->isHttpContext());
        self::assertFalse($report->shouldRender());
        self::assertSame(HotReloadCheck::STATUS_WARN, $byId['frankenphp_hot_reload']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_WARN, $byId['render_gate']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_SKIP, $byId['injected']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_INFO, $byId['caddyfile']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_WARN, $report->getOverallStatus());
    }

    #[Test]
    public function itPassesWhenHttpRequestProvidesFrankenphpEnv(): void
    {
        $request = Request::create('/');
        $request->server->set('FRANKENPHP_HOT_RELOAD', 'https://hub.test/.well-known/mercure');
        $request->attributes->set('_nowo_hot_reload_injected', true);

        $report = $this->createDiagnostics()->evaluate($request, true);
        $byId   = $this->index($report->getChecks());

        self::assertTrue($report->isHttpContext());
        self::assertTrue($report->shouldRender());
        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['frankenphp_hot_reload']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['render_gate']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['injected']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_PASS, $report->getOverallStatus());
    }

    #[Test]
    public function itFailsWhenHttpRequestHasNoEnvAndNoMercureUrl(): void
    {
        $report = $this->createDiagnostics()->evaluate(Request::create('/'));
        $byId   = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_FAIL, $byId['frankenphp_hot_reload']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_FAIL, $byId['render_gate']->getStatus());
        self::assertNotNull($byId['frankenphp_hot_reload']->getFix());
    }

    #[Test]
    public function itWarnsWhenHttpEnvIsMissingButMercureUrlIsConfigured(): void
    {
        $report = $this->createDiagnostics(mercureUrl: 'https://hub.test')->evaluate(Request::create('/'));
        $byId   = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_WARN, $byId['frankenphp_hot_reload']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['mercure_url']->getStatus());
        self::assertTrue($report->shouldRender());
    }

    #[Test]
    public function itFailsWhenDisabled(): void
    {
        $report = $this->createDiagnostics(enabled: false)->evaluate();
        $byId   = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_FAIL, $byId['enabled']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_FAIL, $byId['render_gate']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_SKIP, $byId['injected']->getStatus());
    }

    #[Test]
    public function itFlagsProductionWithoutAllowProduction(): void
    {
        $report = $this->createDiagnostics(environment: 'prod')->evaluate();
        $byId   = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_FAIL, $byId['environment']->getStatus());
    }

    #[Test]
    public function itWarnsWhenProductionIsExplicitlyAllowed(): void
    {
        $report = $this->createDiagnostics(allowProduction: true, environment: 'prod')->evaluate();
        $byId   = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_WARN, $byId['environment']->getStatus());
    }

    #[Test]
    public function itWarnsWhenEnvGateAndAutoInjectAreOff(): void
    {
        $report = $this->createDiagnostics(
            autoInject: false,
            requireFrankenphpEnv: false,
            idiomorph: false,
            cspAugmentScriptSrc: false,
        )->evaluate();
        $byId = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_WARN, $byId['require_frankenphp_env']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_WARN, $byId['auto_inject']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_INFO, $byId['idiomorph']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_WARN, $byId['csp']->getStatus());
        self::assertTrue($report->shouldRender());
        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['render_gate']->getStatus());
    }

    #[Test]
    public function itPassesCspWhenNonceAttributeIsSet(): void
    {
        $report = $this->createDiagnostics(cspNonceRequestAttribute: '_csp_nonce')->evaluate();
        $byId   = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['csp']->getStatus());
    }

    #[Test]
    public function itWarnsWhenHttpGateIsOpenButResponseWasNotInjected(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';
        $request                          = Request::create('/');
        $request->server->set('FRANKENPHP_HOT_RELOAD', 'https://hub.test');

        $report = $this->createDiagnostics()->evaluate($request, false);
        $byId   = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_WARN, $byId['injected']->getStatus());
    }

    #[Test]
    public function itNotesManualTwigWhenAutoInjectIsOffOnHttp(): void
    {
        $report = $this->createDiagnostics(autoInject: false, mercureUrl: 'https://hub.test')
            ->evaluate(Request::create('/'), false);
        $byId = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_INFO, $byId['injected']->getStatus());
        self::assertStringContainsString('nowo_hot_reload_assets', (string) $byId['injected']->getFix());
    }

    #[Test]
    public function itScansCaddyfileDirectives(): void
    {
        $dir = $this->makeTempDir();
        $ok  = $dir . '/good.Caddyfile';
        file_put_contents($ok, "mercure { anonymous }\nphp_server {\n\thot_reload\n\tworker {\n\t\tfile ./index.php\n\t\twatch\n\t}\n}\n");

        $report = $this->createDiagnostics()->evaluate(null, false, $ok);
        $byId   = $this->index($report->getChecks());
        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['caddyfile']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['caddy_mercure']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['caddy_hot_reload']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['caddy_worker_watch']->getStatus());

        $classic = $dir . '/classic.Caddyfile';
        file_put_contents($classic, "mercure { anonymous }\nphp_server { hot_reload }\n");
        $classicReport = $this->createDiagnostics()->evaluate(null, false, $classic);
        self::assertSame(HotReloadCheck::STATUS_INFO, $this->index($classicReport->getChecks())['caddy_worker_watch']->getStatus());

        $bad = $dir . '/bad.Caddyfile';
        file_put_contents($bad, "php_server {\n\tworker {\n\t\tfile ./index.php\n\t}\n}\n");
        $badReport = $this->createDiagnostics()->evaluate(null, false, $bad);
        $badById   = $this->index($badReport->getChecks());
        self::assertSame(HotReloadCheck::STATUS_FAIL, $badById['caddy_mercure']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_FAIL, $badById['caddy_hot_reload']->getStatus());
        self::assertSame(HotReloadCheck::STATUS_WARN, $badById['caddy_worker_watch']->getStatus());
    }

    #[Test]
    public function itFailsWhenCaddyfilePathIsMissing(): void
    {
        $report = $this->createDiagnostics()->evaluate(null, false, '/tmp/does-not-exist-hot-reload.caddy');
        $byId   = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_FAIL, $byId['caddyfile']->getStatus());
    }

    #[Test]
    public function itAutoDetectsCaddyfileFromProjectDir(): void
    {
        $root = $this->makeTempDir();
        mkdir($root . '/docker/frankenphp', 0777, true);
        file_put_contents($root . '/docker/frankenphp/Caddyfile', "mercure {}\nphp_server { hot_reload }\n");

        $report = $this->createDiagnostics(projectDir: $root)->evaluate();
        $byId   = $this->index($report->getChecks());
        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['caddyfile']->getStatus());
        self::assertStringContainsString('Caddyfile', $byId['caddyfile']->getDetail());
    }

    #[Test]
    public function itPrefersClassicCaddyfileDevWhenFrankenphpModeIsClassic(): void
    {
        $root = $this->makeTempDir();
        mkdir($root . '/docker/frankenphp', 0777, true);
        file_put_contents($root . '/docker/frankenphp/Caddyfile', "worker { file ./x }\n");
        file_put_contents($root . '/docker/frankenphp/Caddyfile.dev', "mercure {}\nphp_server { hot_reload }\n");
        $_SERVER['FRANKENPHP_MODE'] = 'classic';

        $report = $this->createDiagnostics(projectDir: $root)->evaluate();
        $byId   = $this->index($report->getChecks());
        self::assertStringContainsString('Caddyfile.dev', $byId['caddyfile']->getDetail());
        self::assertSame(HotReloadCheck::STATUS_INFO, $byId['caddy_worker_watch']->getStatus());
    }

    #[Test]
    public function itReportsInfoWhenProjectDirHasNoCaddyfile(): void
    {
        $report = $this->createDiagnostics(projectDir: $this->makeTempDir())->evaluate();
        $byId   = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_INFO, $byId['caddyfile']->getStatus());
    }

    #[Test]
    public function itReadsFrankenphpModeFromServerAndRootCaddyfile(): void
    {
        $root = $this->makeTempDir();
        file_put_contents($root . '/Caddyfile', "mercure {}\nphp_server { hot_reload }\n");
        $_SERVER['FRANKENPHP_MODE'] = 'worker';

        $report = $this->createDiagnostics(projectDir: $root)->evaluate();
        $byId   = $this->index($report->getChecks());
        self::assertStringContainsString('/Caddyfile', $byId['caddyfile']->getDetail());
    }

    #[Test]
    public function itReadsFrankenphpEnvFromServerSuperglobal(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://from-server.test';
        $report                           = $this->createDiagnostics()->evaluate();
        $byId                             = $this->index($report->getChecks());

        self::assertSame(HotReloadCheck::STATUS_PASS, $byId['frankenphp_hot_reload']->getStatus());
        self::assertTrue($report->shouldRender());
    }

    /**
     * @param list<HotReloadCheck> $checks
     *
     * @return array<string, HotReloadCheck>
     */
    private function index(array $checks): array
    {
        $out = [];
        foreach ($checks as $check) {
            $out[$check->getId()] = $check;
        }

        return $out;
    }

    private function createDiagnostics(
        bool $enabled = true,
        bool $autoInject = true,
        bool $requireFrankenphpEnv = true,
        bool $allowProduction = false,
        ?string $mercureUrl = null,
        string $environment = 'dev',
        bool $idiomorph = true,
        ?string $cspNonceRequestAttribute = null,
        bool $cspAugmentScriptSrc = true,
        ?string $projectDir = null,
    ): HotReloadDiagnostics {
        $assets = new HotReloadAssets(
            enabled: $enabled,
            requireFrankenphpEnv: $requireFrankenphpEnv,
            mercureUrl: $mercureUrl,
            idiomorph: $idiomorph,
            idiomorphScriptUrl: 'https://cdn.example/idiomorph.js',
            hotReloadScriptUrl: 'https://cdn.example/hot-reload.js',
            preserveSelectors: [],
        );

        return new HotReloadDiagnostics(
            assets: $assets,
            enabled: $enabled,
            autoInject: $autoInject,
            requireFrankenphpEnv: $requireFrankenphpEnv,
            allowProduction: $allowProduction,
            mercureUrl: $mercureUrl,
            environment: $environment,
            idiomorph: $idiomorph,
            cspNonceRequestAttribute: $cspNonceRequestAttribute,
            cspAugmentScriptSrc: $cspAugmentScriptSrc,
            projectDir: $projectDir,
        );
    }

    private function makeTempDir(): string
    {
        $this->tempDir = sys_get_temp_dir() . '/hot-reload-diag-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);

        return $this->tempDir;
    }

    private function removeDir(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }
            unlink($path);
        }
        rmdir($dir);
    }
}
