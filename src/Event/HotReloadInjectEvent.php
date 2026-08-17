<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dispatched immediately before Hot Reload assets are written into the HTML response.
 *
 * Hosts can adjust the snippet (e.g. extra attributes) or the response CSP header.
 */
final class HotReloadInjectEvent
{
    public function __construct(
        private readonly Request $request,
        private readonly Response $response,
        private string $snippet,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function setSnippet(string $snippet): void
    {
        $this->snippet = $snippet;
    }
}
