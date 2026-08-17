<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Twig;

use Nowo\HotReloadBundle\HotReloadAssets;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes {{ nowo_hot_reload_assets() }} for manual layout inclusion.
 */
final class HotReloadTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly HotReloadAssets $assets,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_hot_reload_assets', $this->renderAssets(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderAssets(): string
    {
        return $this->assets->renderHtml();
    }
}
