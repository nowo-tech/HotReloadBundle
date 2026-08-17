<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\DataCollector;

use Nowo\HotReloadBundle\EventSubscriber\HotReloadResponseSubscriber;
use Nowo\HotReloadBundle\HotReloadAssets;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Throwable;

use function is_array;
use function is_string;

/**
 * Web Debug Toolbar / Profiler panel for FrankenPHP Hot Reload.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class HotReloadDataCollector implements DataCollectorInterface, LateDataCollectorInterface
{
    public const string NAME = 'nowo_hot_reload';

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
        ];
    }

    public function lateCollect(): void
    {
        if ($this->request instanceof Request) {
            $this->data['injected'] = $this->request->attributes->getBoolean(
                HotReloadResponseSubscriber::REQUEST_ATTR_INJECTED,
            );
        }

        $this->request = null;
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

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
