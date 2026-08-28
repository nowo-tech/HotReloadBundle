<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Client;

use function is_bool;

/**
 * Static catalog of multi-tab approaches for the profiler help panel.
 *
 * @phpstan-type ApproachArray array{
 *     id: string,
 *     title: string,
 *     multi_tab_live: bool|string,
 *     no_slowdown: bool|string,
 *     effort: string,
 *     description: string,
 *     requirements: list<string>,
 *     when_to_use: string
 * }
 * @phpstan-type CompareRowArray array{
 *     id: string,
 *     title: string,
 *     multi_tab_live: string,
 *     no_slowdown: string,
 *     effort: string,
 *     requirements: string
 * }
 */
final class ClientModeGuide
{
    /**
     * @return list<ApproachArray>
     */
    public static function getApproaches(): array
    {
        return [
            [
                'id'             => 'visibility',
                'title'          => 'Visibility-gated EventSource',
                'multi_tab_live' => 'Active tab only',
                'no_slowdown'    => true,
                'effort'         => 'Low',
                'description'    => 'Opens Mercure SSE only while document.visibilityState is visible. Hidden tabs disconnect, freeing HTTP/1.1 connection slots. Background tabs do not live-update until focused.',
                'requirements'   => [
                    'nowo_hot_reload.client_mode: visibility',
                    'Bundle client at /_nowo/hot-reload/client.js',
                    'Optional Idiomorph CDN (or self-host)',
                ],
                'when_to_use' => 'Simple multi-tab HTTP/1.1 setups when only the focused tab needs live updates.',
            ],
            [
                'id'             => 'shared_worker',
                'title'          => 'SharedWorker (single SSE)',
                'multi_tab_live' => true,
                'no_slowdown'    => true,
                'effort'         => 'Medium',
                'description'    => 'One SharedWorker holds a single Mercure EventSource and fans out file-change events to every tab. Visible tabs morph immediately; hidden tabs queue one pending update and apply it on focus.',
                'requirements'   => [
                    'nowo_hot_reload.client_mode: shared_worker',
                    'Browser SharedWorker support',
                    'CSP worker-src allowing \'self\' (same-origin worker script)',
                    'Bundle assets at /_nowo/hot-reload/client.js and /_nowo/hot-reload/shared-worker.js',
                ],
                'when_to_use' => 'Recommended for local HTTP/1.1 when several tabs (e.g. admin) stay open and must stay responsive.',
            ],
            [
                'id'             => 'http2',
                'title'          => 'HTTP/2 local (TLS)',
                'multi_tab_live' => true,
                'no_slowdown'    => true,
                'effort'         => 'Medium–high (infrastructure)',
                'description'    => 'Infrastructure approach (not a client_mode). HTTP/2 multiplexing allows many streams over one connection, so cdn/always modes no longer exhaust the ~6 HTTP/1.1 slots per origin. Requires local TLS (or a reverse proxy that speaks HTTP/2).',
                'requirements'   => [
                    'Serve the app over HTTPS with HTTP/2 (e.g. Caddy TLS locally)',
                    'Client mode can stay cdn or always',
                    'Not configured via nowo_hot_reload.client_mode',
                ],
                'when_to_use' => 'When you already want local HTTPS / HTTP/2 and prefer the stock CDN frankenphp-hot-reload client.',
            ],
            [
                'id'             => 'always',
                'title'          => 'Always-on EventSource per tab',
                'multi_tab_live' => true,
                'no_slowdown'    => 'No on HTTP/1.1',
                'effort'         => 'Low',
                'description'    => 'Bundle client keeps one EventSource open per tab at all times (reconnect on error). Live updates in background tabs, but each tab consumes a persistent connection slot under HTTP/1.1.',
                'requirements'   => [
                    'nowo_hot_reload.client_mode: always',
                    'Prefer HTTP/2 or few open tabs on HTTP/1.1',
                    'Bundle client at /_nowo/hot-reload/client.js',
                ],
                'when_to_use' => 'When you need background-tab updates and already run HTTP/2, or only open one or two tabs.',
            ],
            [
                'id'             => 'cdn',
                'title'          => 'CDN (frankenphp-hot-reload ESM)',
                'multi_tab_live' => true,
                'no_slowdown'    => 'No on HTTP/1.1',
                'effort'         => 'Low (default)',
                'description'    => 'Default backward-compatible mode: Idiomorph CDN + frankenphp-hot-reload ESM module. One EventSource per tab, same multi-tab slot pressure as always under HTTP/1.1.',
                'requirements'   => [
                    'nowo_hot_reload.client_mode: cdn (default)',
                    'script-src allowing the Idiomorph and frankenphp-hot-reload CDN origins (or self-host)',
                ],
                'when_to_use' => 'Default / single-tab workflows; switch to shared_worker or visibility under HTTP/1.1 multi-tab.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function requirementsForMode(ClientMode $mode): array
    {
        foreach (self::getApproaches() as $approach) {
            if ($approach['id'] === $mode->value) {
                return $approach['requirements'];
            }
        }

        return [];
    }

    /**
     * Rows for the profiler comparison HTML table.
     *
     * @return list<CompareRowArray>
     */
    public static function compareTableRows(): array
    {
        $rows = [];
        foreach (self::getApproaches() as $approach) {
            $rows[] = [
                'id'             => $approach['id'],
                'title'          => $approach['title'],
                'multi_tab_live' => self::boolOrStringToLabel($approach['multi_tab_live']),
                'no_slowdown'    => self::boolOrStringToLabel($approach['no_slowdown']),
                'effort'         => $approach['effort'],
                'requirements'   => implode('; ', $approach['requirements']),
            ];
        }

        return $rows;
    }

    private static function boolOrStringToLabel(bool|string $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return $value;
    }
}
