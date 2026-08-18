<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Diagnostics;

use function count;
use function sprintf;

/**
 * Result of {@see HotReloadDiagnostics::evaluate()}.
 *
 * @phpstan-import-type CheckArray from HotReloadCheck
 */
final class HotReloadDiagnosticReport
{
    /**
     * @param list<HotReloadCheck> $checks
     */
    public function __construct(
        private readonly array $checks,
        private readonly bool $httpContext,
        private readonly bool $shouldRender,
        private readonly string $environment,
    ) {
    }

    /**
     * @return list<HotReloadCheck>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    public function isHttpContext(): bool
    {
        return $this->httpContext;
    }

    public function shouldRender(): bool
    {
        return $this->shouldRender;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getPassedCount(): int
    {
        $count = 0;
        foreach ($this->checks as $check) {
            if ($check->isPass()) {
                ++$count;
            }
        }

        return $count;
    }

    public function getFailedCount(): int
    {
        $count = 0;
        foreach ($this->checks as $check) {
            if ($check->isFail()) {
                ++$count;
            }
        }

        return $count;
    }

    public function getWarningCount(): int
    {
        $count = 0;
        foreach ($this->checks as $check) {
            if ($check->isWarn()) {
                ++$count;
            }
        }

        return $count;
    }

    public function getCheckCount(): int
    {
        return count($this->checks);
    }

    public function hasFailures(): bool
    {
        return $this->getFailedCount() > 0;
    }

    public function hasWarnings(): bool
    {
        return $this->getWarningCount() > 0;
    }

    /**
     * Overall roll-up: fail > warn > pass.
     */
    public function getOverallStatus(): string
    {
        if ($this->hasFailures()) {
            return HotReloadCheck::STATUS_FAIL;
        }
        if ($this->hasWarnings()) {
            return HotReloadCheck::STATUS_WARN;
        }

        return HotReloadCheck::STATUS_PASS;
    }

    public function getSummary(): string
    {
        return sprintf(
            '%d passed · %d failed · %d warning(s) (%d checks)',
            $this->getPassedCount(),
            $this->getFailedCount(),
            $this->getWarningCount(),
            $this->getCheckCount(),
        );
    }

    /**
     * @return list<HotReloadCheck>
     */
    public function getActionableChecks(): array
    {
        $out = [];
        foreach ($this->checks as $check) {
            if ($check->isFail() || $check->isWarn()) {
                $out[] = $check;
            }
        }

        return $out;
    }

    /**
     * @return array{
     *     overall: string,
     *     summary: string,
     *     http_context: bool,
     *     should_render: bool,
     *     environment: string,
     *     checks: list<CheckArray>
     * }
     */
    public function toArray(): array
    {
        $checks = [];
        foreach ($this->checks as $check) {
            $checks[] = $check->toArray();
        }

        return [
            'overall'       => $this->getOverallStatus(),
            'summary'       => $this->getSummary(),
            'http_context'  => $this->httpContext,
            'should_render' => $this->shouldRender,
            'environment'   => $this->environment,
            'checks'        => $checks,
        ];
    }
}
