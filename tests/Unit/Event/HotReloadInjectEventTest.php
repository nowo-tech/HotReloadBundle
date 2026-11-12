<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\Event;

use Nowo\HotReloadBundle\Event\HotReloadInjectEvent;
use Nowo\HotReloadBundle\Event\HotReloadInjectEventState;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

use function json_encode;
use function print_r;
use function serialize;
use function unserialize;
use function var_export;

/**
 * 1.7.0: the framework-free accessors of {@see HotReloadInjectEvent} return what the deprecated `getRequest()` /
 * `getResponse()` return, `setBody()` and `getResponse()->setContent()` are the same body, and no dump of the event
 * shows the request or the body.
 */
final class HotReloadInjectEventTest extends TestCase
{
    private const SECRET_COOKIE = 'cookie-value-must-not-leak';
    private const SECRET_BODY   = 'csrf-token-must-not-leak';

    #[Test]
    public function fromContentCarriesScalarsOnly(): void
    {
        $event = HotReloadInjectEvent::fromContent('/blog', 'text/html; charset=UTF-8', '<html>b</html>', '<script></script>');

        self::assertSame('/blog', $event->getPath());
        self::assertSame('text/html; charset=UTF-8', $event->getContentType());
        self::assertSame('<html>b</html>', $event->getBody());
        self::assertSame('<script></script>', $event->getSnippet());

        $event->setBody('<html>changed</html>');
        $event->setSnippet('');
        self::assertSame('<html>changed</html>', $event->getBody());
        self::assertSame('', $event->getSnippet());
        self::assertNull(HotReloadInjectEvent::fromContent('/', null, '', '')->getContentType());
    }

    #[Test]
    #[IgnoreDeprecations]
    public function newAccessorsEqualDeprecatedGetters(): void
    {
        [$request, $response] = $this->http();
        $event                = new HotReloadInjectEvent($request, $response, '<s>');

        self::assertSame($event->getRequest()->getPathInfo(), $event->getPath());
        self::assertSame($event->getResponse()->headers->get('Content-Type'), $event->getContentType());
        self::assertSame($event->getResponse()->getContent(), $event->getBody());
        self::assertSame($request, $event->getRequest());
        self::assertSame($response, $event->getResponse());

        // setBody() and the deprecated getResponse()->setContent() edit the same body.
        $event->setBody('<html>new</html>');
        self::assertSame('<html>new</html>', $response->getContent());
        $event->getResponse()->setContent('<html>legacy</html>');
        self::assertSame('<html>legacy</html>', $event->getBody());
    }

    #[Test]
    #[IgnoreDeprecations]
    public function deprecatedGettersWorkOnAFrameworkFreeEvent(): void
    {
        $event = HotReloadInjectEvent::fromContent('/p', 'text/html', '<html>x</html>', '');

        self::assertSame('/p', $event->getRequest()->getPathInfo());
        $response = $event->getResponse();
        self::assertSame('<html>x</html>', $response->getContent());
        self::assertSame('text/html', $response->headers->get('Content-Type'));
        self::assertSame($response, $event->getResponse());

        $response->setContent('<html>via response</html>');
        self::assertSame('<html>via response</html>', $event->getBody());
        $event->setBody('<html>via event</html>');
        self::assertSame('<html>via event</html>', $response->getContent());

        self::assertNull(HotReloadInjectEvent::fromContent('/q', null, '', '')->getResponse()->headers->get('Content-Type'));
    }

    #[Test]
    #[IgnoreDeprecations]
    public function noDumpOfTheEventShowsTheRequestOrTheBody(): void
    {
        [$request, $response] = $this->http();
        $legacy               = new HotReloadInjectEvent($request, $response, '<s>');
        $attached             = HotReloadInjectEvent::fromContent('/account', 'text/html', '', '<s>');
        HotReloadInjectEventState::attach($attached, $request, $response, '');
        $plain = HotReloadInjectEvent::fromContent('/account', 'text/html', '<p>' . self::SECRET_BODY . '</p>', '<s>');

        foreach ([$legacy, $attached, $plain] as $event) {
            $dumper = new CliDumper();
            $dumper->setColors(false);
            $dumps = [
                (string) $dumper->dump((new VarCloner())->cloneVar($event), true),
                var_export($event, true),
                print_r($event, true),
                print_r((array) $event, true),
                (string) json_encode($event),
                serialize($event),
            ];

            foreach ($dumps as $dump) {
                self::assertStringNotContainsString(self::SECRET_COOKIE, $dump);
                self::assertStringNotContainsString(self::SECRET_BODY, $dump);
                self::assertStringNotContainsString('Authorization', $dump);
            }
            self::assertStringContainsString('/account', $dumps[0]);
        }
    }

    #[Test]
    public function aCloneOrAnUnserializedCopyHasNoHttpState(): void
    {
        $event = HotReloadInjectEvent::fromContent('/c', 'text/html', '<html>' . self::SECRET_BODY . '</html>', '<s>');

        $copy = unserialize(serialize($event));
        self::assertInstanceOf(HotReloadInjectEvent::class, $copy);
        self::assertSame('', $copy->getBody());
        self::assertSame('/c', $copy->getPath());
        self::assertSame('', (clone $event)->getBody());
        self::assertNull(HotReloadInjectEventState::request($event));
    }

    /**
     * @return array{Request, Response}
     */
    private function http(): array
    {
        $request = Request::create('/account', 'GET', [], ['SESSION' => self::SECRET_COOKIE], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . self::SECRET_COOKIE]);

        return [$request, new Response('<html><body>' . self::SECRET_BODY . '</body></html>', 200, ['Content-Type' => 'text/html'])];
    }
}
