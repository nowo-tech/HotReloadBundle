<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\DataCollector;

use Nowo\HotReloadBundle\Diagnostics\HotReloadCheck;
use Nowo\HotReloadBundle\Diagnostics\HotReloadDiagnostics;
use Nowo\HotReloadBundle\EventSubscriber\HotReloadResponseSubscriber;
use Nowo\HotReloadBundle\HotReloadAssets;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Throwable;

use function is_array;
use function is_string;
use function strlen;
use function strpos;
use function substr;

/**
 * Web Debug Toolbar / Profiler panel for FrankenPHP Hot Reload.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class HotReloadDataCollector implements DataCollectorInterface, LateDataCollectorInterface
{
    public const NAME = 'nowo_hot_reload';

    /** Max characters for Mercure URL in the Web Debug Toolbar popover. */
    public const MERCURE_URL_TOOLBAR_MAX = 40;

    /** @var array<string, mixed> */
    private array $data = [];

    private ?Request $request = null;

    /**
     * @param list<string> $preserveSelectors
     * @param list<string> $cspScriptSrcHosts
     */
    public function __construct(
        private readonly HotReloadAssets $assets,
        private readonly bool $autoInject,
        private readonly bool $idiomorph,
        private readonly string $idiomorphScriptUrl,
        private readonly string $hotReloadScriptUrl,
        private readonly array $preserveSelectors,
        private readonly bool $preserveObserve,
        private readonly ?string $cspNonceRequestAttribute,
        private readonly bool $cspAugmentScriptSrc,
        private readonly array $cspScriptSrcHosts,
        private readonly bool $requireFrankenphpEnv,
        private readonly bool $enabled,
        private readonly HotReloadDiagnostics $diagnostics,
    ) {
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function __serialize(): array
    {
        return ['data' => $this->data];
    }

    /**
     * @param array{data?: array<string, mixed>} $data
     */
    public function __unserialize(array $data): void
    {
        $this->data    = $data['data'] ?? [];
        $this->request = null;
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $this->request = $request;

        $frankenphpEnv = $request->server->get('FRANKENPHP_HOT_RELOAD');
        $frankenphpEnv = is_string($frankenphpEnv) && $frankenphpEnv !== '' ? $frankenphpEnv : null;

        $this->data = [
            'enabled'                     => $this->enabled,
            'auto_inject'                 => $this->autoInject,
            'require_frankenphp_env'      => $this->requireFrankenphpEnv,
            'should_render'               => $this->assets->shouldRender(),
            'injected'                    => false,
            'mercure_url'                 => $this->assets->resolveMercureUrl(),
            'frankenphp_hot_reload_env'   => $frankenphpEnv,
            'idiomorph'                   => $this->idiomorph,
            'idiomorph_script_url'        => $this->idiomorphScriptUrl,
            'hot_reload_script_url'       => $this->hotReloadScriptUrl,
            'preserve_selectors'          => $this->preserveSelectors,
            'preserve_observe'            => $this->preserveObserve,
            'csp_nonce_request_attribute' => $this->cspNonceRequestAttribute,
            'csp_augment_script_src'      => $this->cspAugmentScriptSrc,
            'csp_script_src_hosts'        => $this->cspScriptSrcHosts,
            'checks'                      => [],
            'diagnostic_status'           => HotReloadCheck::STATUS_INFO,
            'diagnostic_summary'          => '',
        ];

        $this->storeDiagnosticReport(false);
    }

    public function lateCollect(): void
    {
        if ($this->request instanceof Request) {
            $this->data['injected'] = $this->request->attributes->getBoolean(
                HotReloadResponseSubscriber::REQUEST_ATTR_INJECTED,
            );
            $this->storeDiagnosticReport((bool) $this->data['injected']);
        }

        $this->request = null;
    }

    private function storeDiagnosticReport(bool $injected): void
    {
        $report                           = $this->diagnostics->evaluate($this->request, $injected);
        $this->data['checks']             = $report->toArray()['checks'];
        $this->data['diagnostic_status']  = $report->getOverallStatus();
        $this->data['diagnostic_summary'] = $report->getSummary();
        $this->data['should_render']      = $report->shouldRender();
        $this->data['environment']        = $report->getEnvironment();
    }

    public function reset(): void
    {
        $this->data    = [];
        $this->request = null;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Toolbar / panel status: active | ready | idle | disabled.
     */
    public function getStatus(): string
    {
        if (!($this->data['enabled'] ?? false)) {
            return 'disabled';
        }
        if ($this->data['injected'] ?? false) {
            return 'active';
        }
        if ($this->data['should_render'] ?? false) {
            return 'ready';
        }

        return 'idle';
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->data['enabled'] ?? false);
    }

    public function isAutoInject(): bool
    {
        return (bool) ($this->data['auto_inject'] ?? false);
    }

    public function shouldRender(): bool
    {
        return (bool) ($this->data['should_render'] ?? false);
    }

    public function isInjected(): bool
    {
        return (bool) ($this->data['injected'] ?? false);
    }

    public function getMercureUrl(): ?string
    {
        $url = $this->data['mercure_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Truncated Mercure URL for the toolbar popover (full value stays in {@see getMercureUrl()} / title).
     */
    public function getMercureUrlShort(): ?string
    {
        return $this->shortenUrl($this->getMercureUrl(), self::MERCURE_URL_TOOLBAR_MAX);
    }

    public function getFrankenphpHotReloadEnv(): ?string
    {
        $url = $this->data['frankenphp_hot_reload_env'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function isIdiomorph(): bool
    {
        return (bool) ($this->data['idiomorph'] ?? false);
    }

    public function getIdiomorphScriptUrl(): string
    {
        return (string) ($this->data['idiomorph_script_url'] ?? '');
    }

    public function getHotReloadScriptUrl(): string
    {
        return (string) ($this->data['hot_reload_script_url'] ?? '');
    }

    /**
     * @return list<string>
     */
    public function getPreserveSelectors(): array
    {
        $selectors = $this->data['preserve_selectors'] ?? [];

        return is_array($selectors) ? array_values(array_map('strval', $selectors)) : [];
    }

    public function isPreserveObserve(): bool
    {
        return (bool) ($this->data['preserve_observe'] ?? false);
    }

    public function getCspNonceRequestAttribute(): ?string
    {
        $attr = $this->data['csp_nonce_request_attribute'] ?? null;

        return is_string($attr) && $attr !== '' ? $attr : null;
    }

    public function isCspAugmentScriptSrc(): bool
    {
        return (bool) ($this->data['csp_augment_script_src'] ?? false);
    }

    /**
     * @return list<string>
     */
    public function getCspScriptSrcHosts(): array
    {
        $hosts = $this->data['csp_script_src_hosts'] ?? [];

        return is_array($hosts) ? array_values(array_map('strval', $hosts)) : [];
    }

    public function isRequireFrankenphpEnv(): bool
    {
        return (bool) ($this->data['require_frankenphp_env'] ?? false);
    }

    public function getEnvironment(): string
    {
        return (string) ($this->data['environment'] ?? '');
    }

    public function getDiagnosticStatus(): string
    {
        $status = $this->data['diagnostic_status'] ?? HotReloadCheck::STATUS_INFO;

        return is_string($status) ? $status : HotReloadCheck::STATUS_INFO;
    }

    public function getDiagnosticSummary(): string
    {
        return (string) ($this->data['diagnostic_summary'] ?? '');
    }

    /**
     * @return list<array{id: string, label: string, status: string, detail: string, fix: string|null}>
     */
    public function getChecks(): array
    {
        $checks = $this->data['checks'] ?? [];
        if (!is_array($checks)) {
            return [];
        }

        $out = [];
        foreach ($checks as $check) {
            if (!is_array($check)) {
                continue;
            }
            $out[] = HotReloadCheck::fromArray($check)->toArray();
        }

        return $out;
    }

    /**
     * @return list<array{id: string, label: string, status: string, detail: string, fix: string|null}>
     */
    public function getActionableChecks(): array
    {
        $out = [];
        foreach ($this->getChecks() as $check) {
            if ($check['status'] === HotReloadCheck::STATUS_FAIL || $check['status'] === HotReloadCheck::STATUS_WARN) {
                $out[] = $check;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    private function shortenUrl(?string $url, int $maxLength): ?string
    {
        if ($url === null) {
            return null;
        }
        if (strlen($url) <= $maxLength) {
            return $url;
        }

        $queryPos = strpos($url, '?');
        if ($queryPos !== false && $queryPos >= 8 && $queryPos < $maxLength - 3) {
            return substr($url, 0, $queryPos + 1) . '...';
        }

        return substr($url, 0, $maxLength - 3) . '...';
    }
}
