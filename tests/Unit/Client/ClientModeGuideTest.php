<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\Client;

use Nowo\HotReloadBundle\Client\ClientMode;
use Nowo\HotReloadBundle\Client\ClientModeGuide;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientModeGuideTest extends TestCase
{
    #[Test]
    public function itListsAllApproachesIncludingHttp2Infra(): void
    {
        $ids = array_column(ClientModeGuide::getApproaches(), 'id');

        self::assertSame(['visibility', 'shared_worker', 'http2', 'always', 'cdn'], $ids);
    }

    #[Test]
    public function itReturnsRequirementsForSharedWorker(): void
    {
        $reqs = ClientModeGuide::requirementsForMode(ClientMode::SharedWorker);

        self::assertNotEmpty($reqs);
        self::assertStringContainsString('shared_worker', implode(' ', $reqs));
    }

    #[Test]
    public function itBuildsCompareTableRows(): void
    {
        $rows = ClientModeGuide::compareTableRows();

        self::assertCount(5, $rows);
        self::assertArrayHasKey('multi_tab_live', $rows[0]);
        self::assertArrayHasKey('requirements', $rows[0]);
        self::assertIsString($rows[0]['requirements']);
    }

    #[Test]
    public function tryFromConfigFallsBackToCdn(): void
    {
        self::assertSame(ClientMode::Cdn, ClientMode::tryFromConfig(null));
        self::assertSame(ClientMode::Cdn, ClientMode::tryFromConfig('nope'));
        self::assertSame(ClientMode::SharedWorker, ClientMode::tryFromConfig('shared_worker'));
        self::assertTrue(ClientMode::SharedWorker->isBundleClient());
        self::assertFalse(ClientMode::Cdn->isBundleClient());
    }

    #[Test]
    public function itExposesHumanReadableLabelsForEveryMode(): void
    {
        self::assertStringContainsString('CDN', ClientMode::Cdn->label());
        self::assertStringContainsString('Visibility', ClientMode::Visibility->label());
        self::assertStringContainsString('SharedWorker', ClientMode::SharedWorker->label());
        self::assertStringContainsString('Always', ClientMode::Always->label());
    }
}
