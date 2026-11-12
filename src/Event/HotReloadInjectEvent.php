<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Event;

use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function trigger_deprecation;

/**
 * Dispatched immediately before Hot Reload assets are written into the HTML response.
 *
 * Listeners read the request path and the response content type, change the HTML body ({@see setBody()}) and change or
 * cancel the snippet ({@see setSnippet()}; `''` cancels the injection). Since 1.7.0 these accessors carry scalars only
 * (REQ-ARCH-001 rule 3), so a listener needs no HttpFoundation type; the subscriber injects the snippet into the body
 * as the listeners left it. Response headers (CSP) are adjusted through the `csp_*` options or a standard
 * `kernel.response` listener.
 *
 * The request, the response and the body are not stored in the event's properties (see
 * {@see HotReloadInjectEventState}), so dumping the event shows only the path, the content type and the snippet.
 */
final class HotReloadInjectEvent
{
    private string $path;

    private ?string $contentType;

    private string $snippet;

    /**
     * @deprecated since 1.7.0, constructing the event with HttpFoundation objects is reserved to the bundle; use
     *             {@see fromContent()} (removed in 2.0)
     */
    public function __construct(Request $request, Response $response, string $snippet)
    {
        trigger_deprecation('nowo-tech/hot-reload-bundle', '1.7', 'Constructing "%s" with a Request and a Response is deprecated, use "%s::fromContent()" instead.', self::class, self::class);

        $this->initialize($request->getPathInfo(), $response->headers->get('Content-Type'), $snippet);
        HotReloadInjectEventState::attach($this, $request, $response, '');
    }

    /**
     * Framework-free factory (since 1.7.0): the event for an HTML body about to receive `$snippet`.
     *
     * @param string $path the request path (no query string)
     * @param string|null $contentType the response `Content-Type` header, null when absent
     */
    public static function fromContent(string $path, ?string $contentType, string $body, string $snippet): self
    {
        $event = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $event->initialize($path, $contentType, $snippet);
        HotReloadInjectEventState::attach($event, null, null, $body);

        return $event;
    }

    /** The request path (path info, no query string). Since 1.7.0. */
    public function getPath(): string
    {
        return $this->path;
    }

    /** The response `Content-Type` header when the event was created, null when absent. Since 1.7.0. */
    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    /** The HTML body the snippet will be injected into, as the listeners left it so far. Since 1.7.0. */
    public function getBody(): string
    {
        return HotReloadInjectEventState::body($this);
    }

    /** Replaces the HTML body; the snippet is injected into it afterwards. Since 1.7.0. */
    public function setBody(string $body): void
    {
        HotReloadInjectEventState::setBody($this, $body);
    }

    /**
     * @deprecated since 1.7.0, use {@see getPath()} (removed in 2.0)
     */
    public function getRequest(): Request
    {
        trigger_deprecation('nowo-tech/hot-reload-bundle', '1.7', 'Calling "%s()" is deprecated, use "getPath()" instead.', __METHOD__);

        return HotReloadInjectEventState::request($this) ?? Request::create($this->path);
    }

    /**
     * @deprecated since 1.7.0, use {@see getContentType()}, {@see getBody()} and {@see setBody()}; adjust headers
     *             with a `kernel.response` listener (removed in 2.0)
     */
    public function getResponse(): Response
    {
        trigger_deprecation('nowo-tech/hot-reload-bundle', '1.7', 'Calling "%s()" is deprecated, use "getContentType()", "getBody()" and "setBody()" instead.', __METHOD__);

        $response = HotReloadInjectEventState::response($this);
        if ($response instanceof Response) {
            return $response;
        }

        // Framework-free event: give the legacy caller a response bound to the event's body.
        $response = new Response($this->getBody(), 200, $this->contentType === null ? [] : ['Content-Type' => $this->contentType]);
        HotReloadInjectEventState::attach($this, HotReloadInjectEventState::request($this), $response, '');

        return $response;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function setSnippet(string $snippet): void
    {
        // @igor-ignore - Request-scoped event DTO; not a shared worker service
        $this->snippet = $snippet;
    }

    private function initialize(string $path, ?string $contentType, string $snippet): void
    {
        // @igor-ignore - Request-scoped event DTO; set once on creation
        $this->path = $path;
        // @igor-ignore - Request-scoped event DTO; set once on creation
        $this->contentType = $contentType;
        // @igor-ignore - Request-scoped event DTO; not a shared worker service
        $this->snippet = $snippet;
    }
}
