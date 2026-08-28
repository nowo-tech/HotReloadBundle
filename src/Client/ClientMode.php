<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Client;

/**
 * How the browser connects to Mercure for FrankenPHP Hot Reload.
 */
enum ClientMode: string
{
    case Cdn          = 'cdn';
    case Visibility   = 'visibility';
    case SharedWorker = 'shared_worker';
    case Always       = 'always';

    /**
     * Resolve a config string to a mode; unknown values fall back to {@see self::Cdn}.
     */
    public static function tryFromConfig(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::Cdn;
        }

        return self::tryFrom($value) ?? self::Cdn;
    }

    public function label(): string
    {
        return match ($this) {
            self::Cdn          => 'CDN (frankenphp-hot-reload ESM)',
            self::Visibility   => 'Visibility-gated EventSource',
            self::SharedWorker => 'SharedWorker (single SSE)',
            self::Always       => 'Always-on EventSource per tab',
        };
    }

    /**
     * Whether the bundle serves `/_nowo/hot-reload/client.js` instead of the CDN ESM module.
     */
    public function isBundleClient(): bool
    {
        return $this !== self::Cdn;
    }
}
