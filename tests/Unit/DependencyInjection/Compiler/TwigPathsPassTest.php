<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\HotReloadBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\HotReloadBundle\NowoHotReloadBundle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader;

use function dirname;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

final class TwigPathsPassTest extends TestCase
{
    #[Test]
    public function itDoesNothingWhenTwigLoaderIsAbsent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.native'));
    }

    #[Test]
    public function itAddsBundleViewsPathToNativeLoader(): void
    {
        $definition = $this->processWithLoader('twig.loader.native');

        $addPathCalls = $this->methodCalls($definition, 'addPath');
        self::assertCount(2, $addPathCalls);

        $canonicalPath = $addPathCalls[0][1][0] ?? null;
        self::assertIsString($canonicalPath);
        self::assertStringContainsString('/templates', $canonicalPath);
        self::assertSame(TwigPathsPass::TWIG_NAMESPACE, $addPathCalls[0][1][1]);

        $legacyPath = $addPathCalls[1][1][0] ?? null;
        self::assertIsString($legacyPath);
        self::assertStringContainsString('/templates', $legacyPath);
        self::assertSame(TwigPathsPass::TWIG_NAMESPACE_LEGACY, $addPathCalls[1][1][1]);

        self::assertSame([], $this->methodCalls($definition, 'prependPath'));
    }

    #[Test]
    public function itUsesNativeFilesystemLoaderDefinition(): void
    {
        $definition = $this->processWithLoader('twig.loader.native_filesystem');

        self::assertNotEmpty($this->methodCalls($definition, 'addPath'));
    }

    #[Test]
    public function itResolvesChainedAliasToDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $definition = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.real', $definition);
        $container->setAlias('twig.loader.chain', 'twig.loader.real');
        $container->setAlias('twig.loader.native', 'twig.loader.chain');

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($this->methodCalls($definition, 'addPath'));
    }

    #[Test]
    public function itFallsThroughToNativeFilesystemWhenAliasAndNativeAreMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setAlias('twig.loader.native', 'twig.loader.missing');

        $definition = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native_filesystem', $definition);

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($this->methodCalls($definition, 'addPath'));
    }

    #[Test]
    public function itIgnoresAliasWithoutDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setAlias('twig.loader.native', 'twig.loader.missing');

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.missing'));
    }

    #[Test]
    public function itStillAddsPathsWhenProjectDirParameterIsMissing(): void
    {
        $container  = new ContainerBuilder();
        $definition = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native', $definition);

        (new TwigPathsPass())->process($container);

        self::assertCount(2, $this->methodCalls($definition, 'addPath'));
        self::assertSame([], $this->methodCalls($definition, 'prependPath'));
    }

    #[Test]
    public function itStillAddsPathsWhenProjectDirIsNotAString(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', 123);

        $definition = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native', $definition);

        (new TwigPathsPass())->process($container);

        self::assertCount(2, $this->methodCalls($definition, 'addPath'));
        self::assertSame([], $this->methodCalls($definition, 'prependPath'));
    }

    #[Test]
    public function itPrependsOverridePathWhenDirectoryExists(): void
    {
        $projectDir  = sys_get_temp_dir() . '/hr_project_' . uniqid('', true);
        $overrideDir = $projectDir . '/templates/bundles/' . TwigPathsPass::TWIG_NAMESPACE;
        mkdir($overrideDir, 0777, true);

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $projectDir);

            $definition = new Definition(FilesystemLoader::class);
            $container->setDefinition('twig.loader.native', $definition);

            (new TwigPathsPass())->process($container);

            $prependCalls = $this->methodCalls($definition, 'prependPath');
            self::assertCount(1, $prependCalls);
            $prependPath = $prependCalls[0][1][0] ?? null;
            self::assertIsString($prependPath);
            self::assertSame($overrideDir, $prependPath);
            self::assertSame(TwigPathsPass::TWIG_NAMESPACE, $prependCalls[0][1][1]);
        } finally {
            rmdir($overrideDir);
            rmdir(dirname($overrideDir));
            rmdir(dirname($overrideDir, 2));
            rmdir($projectDir);
        }
    }

    #[Test]
    public function itIsRegisteredOnBundleBuild(): void
    {
        $container = new ContainerBuilder();
        (new NowoHotReloadBundle())->build($container);

        $found = false;
        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            if ($pass instanceof TwigPathsPass) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found);
    }

    /**
     * @return list<array{0: string, 1: list<mixed>}>
     */
    private function methodCalls(Definition $definition, string $method): array
    {
        $matched = [];
        foreach ($definition->getMethodCalls() as $call) {
            if ($call[0] === $method) {
                $matched[] = $call;
            }
        }

        return $matched;
    }

    private function processWithLoader(string $serviceId): Definition
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $definition = new Definition(FilesystemLoader::class);
        $container->setDefinition($serviceId, $definition);

        (new TwigPathsPass())->process($container);

        return $definition;
    }
}
