<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Command;

use Nowo\HotReloadBundle\Diagnostics\HotReloadCheck;
use Nowo\HotReloadBundle\Diagnostics\HotReloadDiagnosticReport;
use Nowo\HotReloadBundle\Diagnostics\HotReloadDiagnostics;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_string;
use function json_encode;
use function sprintf;
use function strtoupper;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Validates FrankenPHP Hot Reload environment and bundle configuration.
 */
#[AsCommand(name: 'nowo:hot-reload:check', description: 'Validate FrankenPHP Hot Reload environment and print what is missing', help: <<<'TXT'
Validates nowo_hot_reload config and the FrankenPHP environment.

CLI does not receive FRANKENPHP_HOT_RELOAD (FrankenPHP injects it on HTTP
requests). A warning in that row is expected; confirm the same checks on the
Web Debug Toolbar profiler panel after loading an HTML page.

Examples:
  php bin/console nowo:hot-reload:check
  php bin/console nowo:hot-reload:check --caddyfile=docker/frankenphp/Caddyfile
  php bin/console nowo:hot-reload:check --json --strict

Full Caddy / Docker / troubleshooting guide: docs/ENVIRONMENT.md
TXT)]
final class HotReloadCheckCommand extends Command
{
    public function __construct(
        private readonly HotReloadDiagnostics $diagnostics,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'caddyfile',
                null,
                InputOption::VALUE_REQUIRED,
                'Caddyfile to scan for mercure / hot_reload / worker watch (default: auto-detect)',
            )
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit with failure when there are warnings');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $caddyfile = $input->getOption('caddyfile');
        $caddyfile = is_string($caddyfile) && $caddyfile !== '' ? $caddyfile : null;
        $report    = $this->diagnostics->evaluate(null, false, $caddyfile);
        $asJson    = $input->getOption('json') === true;
        $strict    = $input->getOption('strict') === true;

        if ($asJson) {
            $output->writeln(json_encode(
                $report->toArray(),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return $this->exitCode($report, $strict);
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('FrankenPHP Hot Reload checks');
        $io->text(sprintf(
            'kernel.environment=<info>%s</info> · context=CLI · render gate: <comment>%s</comment>',
            $report->getEnvironment(),
            $report->shouldRender() ? 'open' : 'closed',
        ));
        $io->newLine();

        $rows = [];
        foreach ($report->getChecks() as $check) {
            $rows[] = [
                $this->statusLabel($check->getStatus()),
                $check->getLabel(),
                $check->getDetail(),
            ];
        }
        $io->table(['Status', 'Check', 'Detail'], $rows);

        $actionable = $report->getActionableChecks();
        if ($actionable !== []) {
            $io->section('What is missing / what to do');
            foreach ($actionable as $check) {
                $fix = $check->getFix() ?? $check->getDetail();
                $io->writeln(sprintf(' * <comment>%s</comment>: %s', $check->getLabel(), $fix));
            }
            $io->newLine();
        }

        $summary = $report->getSummary();
        if ($report->hasFailures()) {
            $io->error($summary);
        } elseif ($report->hasWarnings()) {
            $io->warning($summary);
        } else {
            $io->success($summary);
        }

        return $this->exitCode($report, $strict);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            HotReloadCheck::STATUS_PASS => '<info>PASS</info>',
            HotReloadCheck::STATUS_FAIL => '<error>FAIL</error>',
            HotReloadCheck::STATUS_WARN => '<comment>WARN</comment>',
            HotReloadCheck::STATUS_SKIP => 'SKIP',
            default                     => strtoupper($status),
        };
    }

    private function exitCode(HotReloadDiagnosticReport $report, bool $strict): int
    {
        if ($report->hasFailures()) {
            return Command::FAILURE;
        }
        if ($strict && $report->hasWarnings()) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
