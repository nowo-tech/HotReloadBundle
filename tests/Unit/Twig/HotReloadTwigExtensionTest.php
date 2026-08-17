<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\Twig;

use Nowo\HotReloadBundle\HotReloadAssets;
use Nowo\HotReloadBundle\Twig\HotReloadTwigExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HotReloadTwigExtensionTest extends TestCase
{
    #[Test]
    public function itExposesSafeHtmlFunction(): void
    {
        $assets = new HotReloadAssets(
            enabled: true,
            requireFrankenphpEnv: false,
            mercureUrl: 'https://hub.test',
            idiomorph: false,
            idiomorphScriptUrl: 'https://cdn.jsdelivr.net/npm/idiomorph',
            hotReloadScriptUrl: 'https://cdn.jsdelivr.net/npm/frankenphp-hot-reload/+esm',
            preserveSelectors: [],
        );
        $extension = new HotReloadTwigExtension($assets);
        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('nowo_hot_reload_assets', $functions[0]->getName());
        self::assertStringContainsString('frankenphp-hot-reload:url', $extension->renderAssets());
    }
}
