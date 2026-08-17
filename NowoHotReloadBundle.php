<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle;

use Nowo\HotReloadBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\HotReloadBundle\DependencyInjection\HotReloadExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle that injects FrankenPHP (Dunglas) Hot Reload client assets in development.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class NowoHotReloadBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TwigPathsPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new HotReloadExtension();
        }

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }
}
