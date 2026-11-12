<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WeakMap;

/**
 * Holds the HTTP objects and the body of each {@see HotReloadInjectEvent} outside the event's properties, so no dump of
 * the event (VarDumper / `dump()`, `var_export()`, `print_r()`, `(array)` cast, `json_encode()`, `serialize()`) shows
 * the request (cookies, session, `Authorization`) or the response body (CSRF tokens, personal data).
 *
 * Keyed by the event object in a {@see WeakMap}: an entry disappears with its event, so nothing outlives the response
 * in a FrankenPHP worker (no reset needed, REQ-RUNTIME-002). A `clone` or an unserialized copy of an event has no entry.
 *
 * @internal
 */
final class HotReloadInjectEventState
{
    /** @var WeakMap<HotReloadInjectEvent, array{request: Request|null, response: Response|null, body: string}>|null */
    private static ?WeakMap $states = null; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty (WeakMap keyed by the per-response event)

    public static function attach(HotReloadInjectEvent $event, ?Request $request, ?Response $response, string $body): void
    {
        // @igor-ignore - WeakMap keyed by the per-response event; entries are collected with it.
        self::$states ??= new WeakMap(); // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
        // @igor-ignore - WeakMap keyed by the per-response event; entries are collected with it.
        self::$states[$event] = ['request' => $request, 'response' => $response, 'body' => $body]; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
    }

    public static function request(HotReloadInjectEvent $event): ?Request
    {
        return self::$states[$event]['request'] ?? null;
    }

    public static function response(HotReloadInjectEvent $event): ?Response
    {
        return self::$states[$event]['response'] ?? null;
    }

    /** The current body: the attached response's content, else the stored body ('' without an entry). */
    public static function body(HotReloadInjectEvent $event): string
    {
        $response = self::response($event);
        if ($response instanceof Response) {
            $content = $response->getContent();

            return $content === false ? '' : $content;
        }

        return self::$states[$event]['body'] ?? '';
    }

    /** Replaces the body: on the attached response when there is one, else in the stored state. */
    public static function setBody(HotReloadInjectEvent $event, string $body): void
    {
        $response = self::response($event);
        if ($response instanceof Response) {
            // @igor-ignore - the per-response Response the event was created for, not a shared service
            $response->setContent($body);

            return;
        }

        self::attach($event, self::request($event), null, $body);
    }
}
