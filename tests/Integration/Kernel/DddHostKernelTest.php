<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Integration\Kernel;

use Nowo\HotReloadBundle\HotReloadAssets;
use Nowo\HotReloadBundle\Tests\Fixtures\DddHost\DddHostKernel;
use Nowo\HotReloadBundle\Tests\Fixtures\DddHost\PageBannerListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function file_get_contents;
use function gc_collect_cycles;
use function memory_get_usage;
use function preg_match;
use function preg_match_all;
use function sys_get_temp_dir;
use function uniqid;

/**
 * REQ-ARCH-001 (DDD host) and REQ-RUNTIME-002: a host listener on the framework-free accessors of the inject event
 * (no HttpFoundation import) changes the body and cancels injection per path; `lint:container` passes; 600 requests
 * through one kept-alive `http_kernel` without the services resetter, alternating three paths, each see only their own
 * path, and memory stays flat.
 */
final class DddHostKernelTest extends TestCase
{
    private const REQUESTS = 600;

    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/hr_ddd_host_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);
    }

    #[Test]
    public function hostListenerUsesTheFrameworkFreeAccessorsAcrossManyRequests(): void
    {
        $kernel = new DddHostKernel($this->baseDir);
        $kernel->boot();

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('lint:container'));
        self::assertSame(0, $tester->execute([]), $tester->getDisplay());

        $httpKernel = $kernel->getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernelInterface::class, $httpKernel);

        $paths    = ['/alice', '/bob', '/no-reload/carol'];
        $baseline = 0;
        for ($i = 0; $i < self::REQUESTS; ++$i) {
            $path     = $paths[$i % 3];
            $response = $httpKernel->handle(Request::create($path));
            $content  = (string) $response->getContent();

            self::assertSame(200, $response->getStatusCode());
            if ($path === '/no-reload/carol') {
                self::assertStringNotContainsString(HotReloadAssets::MARKER, $content, $path);
                self::assertStringNotContainsString('data-banner', $content, $path);
            } else {
                self::assertStringContainsString(HotReloadAssets::MARKER, $content, $path);
                self::assertStringContainsString('<body data-banner="' . $path . '|text/html; charset=UTF-8">', $content, $path);
                self::assertSame(1, preg_match_all('/data-banner=/', $content), $path);
            }

            if ($i === 30) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $baseline, 'memory grows across requests');

        $kernel->shutdown();
    }

    #[Test]
    public function hostListenerImportsNoFrameworkType(): void
    {
        $file = (string) (new ReflectionClass(PageBannerListener::class))->getFileName();
        self::assertSame(0, preg_match('/^use (Doctrine\\\\|Symfony\\\\)/m', (string) file_get_contents($file)));
    }
}
