<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\Diagnostics;

use Nowo\HotReloadBundle\Diagnostics\HotReloadCheck;
use Nowo\HotReloadBundle\Diagnostics\HotReloadDiagnosticReport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HotReloadDiagnosticReportTest extends TestCase
{
    #[Test]
    public function itRollsUpStatusCountsAndJsonShape(): void
    {
        $report = new HotReloadDiagnosticReport(
            [
                new HotReloadCheck('a', 'A', HotReloadCheck::STATUS_PASS, 'ok'),
                new HotReloadCheck('b', 'B', HotReloadCheck::STATUS_FAIL, 'no', 'fix'),
                new HotReloadCheck('c', 'C', HotReloadCheck::STATUS_WARN, 'hmm', 'look'),
                new HotReloadCheck('d', 'D', HotReloadCheck::STATUS_INFO, 'note'),
            ],
            true,
            true,
            'dev',
        );

        self::assertTrue($report->isHttpContext());
        self::assertTrue($report->shouldRender());
        self::assertSame('dev', $report->getEnvironment());
        self::assertSame(4, $report->getCheckCount());
        self::assertSame(1, $report->getPassedCount());
        self::assertSame(1, $report->getFailedCount());
        self::assertSame(1, $report->getWarningCount());
        self::assertTrue($report->hasFailures());
        self::assertTrue($report->hasWarnings());
        self::assertSame(HotReloadCheck::STATUS_FAIL, $report->getOverallStatus());
        self::assertCount(2, $report->getActionableChecks());
        self::assertStringContainsString('1 passed', $report->getSummary());

        $payload = $report->toArray();
        self::assertSame('fail', $payload['overall']);
        self::assertTrue($payload['http_context']);
        self::assertTrue($payload['should_render']);
        self::assertCount(4, $payload['checks']);
    }

    #[Test]
    public function itPrefersWarnOverPassWhenNothingFailed(): void
    {
        $warnOnly = new HotReloadDiagnosticReport(
            [new HotReloadCheck('a', 'A', HotReloadCheck::STATUS_WARN, 'w')],
            false,
            false,
            'test',
        );
        self::assertSame(HotReloadCheck::STATUS_WARN, $warnOnly->getOverallStatus());

        $passOnly = new HotReloadDiagnosticReport(
            [new HotReloadCheck('a', 'A', HotReloadCheck::STATUS_PASS, 'ok')],
            false,
            true,
            'test',
        );
        self::assertSame(HotReloadCheck::STATUS_PASS, $passOnly->getOverallStatus());
        self::assertFalse($passOnly->hasFailures());
        self::assertFalse($passOnly->hasWarnings());
    }
}
