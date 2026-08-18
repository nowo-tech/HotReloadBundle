<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\Command;

use Nowo\HotReloadBundle\Command\HotReloadCheckCommand;
use Nowo\HotReloadBundle\Diagnostics\HotReloadDiagnostics;
use Nowo\HotReloadBundle\HotReloadAssets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function file_put_contents;
use function json_decode;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;

final class HotReloadCheckCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['FRANKENPHP_HOT_RELOAD']);
        parent::tearDown();
    }

    #[Test]
    public function itPrintsATableAndSucceedsWhenOnlyWarningsRemain(): void
    {
        $tester = $this->tester();
        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('FrankenPHP Hot Reload checks', $tester->getDisplay());
        self::assertStringContainsString('What is missing / what to do', $tester->getDisplay());
        self::assertStringContainsString('FRANKENPHP_HOT_RELOAD', $tester->getDisplay());
    }

    #[Test]
    public function itFailsWhenTheBundleIsDisabled(): void
    {
        $tester = $this->tester(enabled: false);
        $status = $tester->execute([]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('FAIL', $tester->getDisplay());
    }

    #[Test]
    public function itReturnsFailureInJsonModeWhenChecksFail(): void
    {
        $tester = $this->tester(enabled: false);
        $status = $tester->execute(['--json' => true]);

        self::assertSame(Command::FAILURE, $status);
        $payload = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('fail', $payload['overall']);
    }

    #[Test]
    public function itFailsOnWarningsWhenStrict(): void
    {
        $tester = $this->tester();
        $status = $tester->execute(['--strict' => true]);

        self::assertSame(Command::FAILURE, $status);
    }

    #[Test]
    public function itOutputsJson(): void
    {
        $tester = $this->tester(mercureUrl: 'https://hub.test');
        $status = $tester->execute(['--json' => true]);

        self::assertSame(Command::SUCCESS, $status);
        $payload = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertArrayHasKey('checks', $payload);
        self::assertArrayHasKey('overall', $payload);
        self::assertSame('dev', $payload['environment']);
    }

    #[Test]
    public function itScansAnExplicitCaddyfile(): void
    {
        $path = sys_get_temp_dir() . '/hot-reload-cmd-' . uniqid('', true) . '.Caddyfile';
        file_put_contents($path, "mercure { anonymous }\nphp_server { hot_reload }\n");

        $tester = $this->tester(mercureUrl: 'https://hub.test');
        $status = $tester->execute(['--caddyfile' => $path, '--json' => true]);
        self::assertSame(Command::SUCCESS, $status);

        $payload = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        $ids     = [];
        foreach ($payload['checks'] as $check) {
            $ids[$check['id']] = $check['status'];
        }
        self::assertSame('pass', $ids['caddy_mercure']);
        self::assertSame('pass', $ids['caddy_hot_reload']);
        unlink($path);
    }

    #[Test]
    public function itSucceedsWithACleanHttpLikeEnvOnCliWhenMercureUrlIsSet(): void
    {
        $_SERVER['FRANKENPHP_HOT_RELOAD'] = 'https://hub.test';
        $tester                           = $this->tester();
        $status                           = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('PASS', $tester->getDisplay());
        self::assertStringNotContainsString('What is missing', $tester->getDisplay());
    }

    private function tester(
        bool $enabled = true,
        ?string $mercureUrl = null,
    ): CommandTester {
        $assets = new HotReloadAssets(
            enabled: $enabled,
            requireFrankenphpEnv: true,
            mercureUrl: $mercureUrl,
            idiomorph: true,
            idiomorphScriptUrl: 'https://cdn.example/i.js',
            hotReloadScriptUrl: 'https://cdn.example/h.js',
            preserveSelectors: [],
        );
        $diagnostics = new HotReloadDiagnostics(
            assets: $assets,
            enabled: $enabled,
            autoInject: true,
            requireFrankenphpEnv: true,
            allowProduction: false,
            mercureUrl: $mercureUrl,
            environment: 'dev',
            idiomorph: true,
            cspNonceRequestAttribute: null,
            cspAugmentScriptSrc: true,
        );

        return new CommandTester(new HotReloadCheckCommand($diagnostics));
    }
}
