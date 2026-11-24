<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\Templates;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ProfilerTemplateTest extends TestCase
{
    private const TEMPLATE = __DIR__ . '/../../../templates/Collector/hot_reload.html.twig';

    #[Test]
    public function itHasNoInlineStyleAttribute(): void
    {
        $source = (string) file_get_contents(self::TEMPLATE);

        self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $source);
    }

    #[Test]
    public function itCarriesTheEllipsisClassInANoncedStyleBlock(): void
    {
        $source = (string) file_get_contents(self::TEMPLATE);

        self::assertStringContainsString('nowo-hot-reload-ellipsis', $source);
        self::assertStringContainsString('csp_style_nonce', $source);
        self::assertSame(2, substr_count($source, 'class="nowo-hot-reload-ellipsis '));
    }

    #[Test]
    public function itRendersTheNonceOnTheStyleBlock(): void
    {
        $source = (string) file_get_contents(self::TEMPLATE);
        $twig   = new Environment(new ArrayLoader([]));
        // Only the toolbar block's <style> fragment is rendered; the profiler layout is not available in the bundle's own tests.
        preg_match('/(<style\{%.*?<\/style>)/s', $source, $matches);
        self::assertNotEmpty($matches);
        $template = $twig->createTemplate($matches[1]);

        $with    = $template->render(['csp_style_nonce' => 'abc123']);
        $without = $template->render([]);

        self::assertStringContainsString('<style nonce="abc123">', $with);
        self::assertStringContainsString('<style>', $without);
        self::assertStringNotContainsString('style=', $with . $without);
    }
}
