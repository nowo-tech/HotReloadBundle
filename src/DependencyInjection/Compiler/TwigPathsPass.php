<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function dirname;
use function is_dir;
use function is_string;
use function rtrim;

/**
 * Registers the bundle Twig namespace on the native filesystem loader so
 * logical names `@NowoHotReloadBundle/...` resolve, and application overrides
 * under `templates/bundles/NowoHotReloadBundle/` win.
 */
final class TwigPathsPass implements CompilerPassInterface
{
    public const TWIG_NAMESPACE = 'NowoHotReloadBundle';

    /** Symfony default namespace (strips the Bundle suffix); kept as a BC alias. */
    public const TWIG_NAMESPACE_LEGACY = 'NowoHotReload';

    public function process(ContainerBuilder $container): void
    {
        $loaderId = $this->getNativeLoaderServiceId($container);
        if ($loaderId === null) {
            return;
        }

        $viewsPath  = dirname(__DIR__, 3) . '/templates';
        $definition = $container->getDefinition($loaderId);

        if ($container->hasParameter('kernel.project_dir')) {
            $projectDirParam = $container->getParameter('kernel.project_dir');
            if (is_string($projectDirParam)) {
                $projectDir   = rtrim($projectDirParam, '/\\');
                $overridePath = $projectDir . '/templates/bundles/' . self::TWIG_NAMESPACE;
                if (is_dir($overridePath)) {
                    $definition->addMethodCall('prependPath', [$overridePath, self::TWIG_NAMESPACE]);
                }
            }
        }

        $definition->addMethodCall('addPath', [$viewsPath, self::TWIG_NAMESPACE]);
        $definition->addMethodCall('addPath', [$viewsPath, self::TWIG_NAMESPACE_LEGACY]);
    }

    private function getNativeLoaderServiceId(ContainerBuilder $container): ?string
    {
        if ($container->hasAlias('twig.loader.native')) {
            $resolved = $this->resolveDefinitionId($container, (string) $container->getAlias('twig.loader.native'));
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($container->hasDefinition('twig.loader.native')) {
            return 'twig.loader.native';
        }

        if ($container->hasDefinition('twig.loader.native_filesystem')) {
            return 'twig.loader.native_filesystem';
        }

        return null;
    }

    private function resolveDefinitionId(ContainerBuilder $container, string $id): ?string
    {
        for ($i = 0; $i < 32 && $container->hasAlias($id); ++$i) {
            $id = (string) $container->getAlias($id);
        }

        return $container->hasDefinition($id) ? $id : null;
    }
}
