<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Fixtures\DddHost;

use Nowo\HotReloadBundle\Event\HotReloadInjectEvent;
use Nowo\HotReloadBundle\NowoHotReloadBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function dirname;
use function htmlspecialchars;

/**
 * Host kernel whose only Hot Reload extension point is {@see PageBannerListener}, a listener on the framework-free
 * accessors of {@see HotReloadInjectEvent}.
 */
final class DddHostKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        private readonly string $baseDir,
    ) {
        parent::__construct('dev', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new NowoHotReloadBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return $this->baseDir . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->baseDir . '/log';
    }

    public function page(Request $request): Response
    {
        return new Response('<html><head></head><body>' . htmlspecialchars($request->getPathInfo()) . '</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'test-only-not-a-credential',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'php_errors'           => ['log' => true],
        ]);
        $container->extension('nowo_hot_reload', [
            'enabled'     => true,
            'mercure_url' => 'https://hub.test/.well-known/mercure',
        ]);

        $container->services()
            ->set(PageBannerListener::class)
            ->tag('kernel.event_listener', ['event' => HotReloadInjectEvent::class]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('page', '/{slug}')->controller([self::class, 'page'])->requirements(['slug' => '.*'])->defaults(['slug' => '']);
    }
}
