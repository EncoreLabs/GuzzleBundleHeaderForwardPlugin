<?php

namespace EncoreLabs\Bundle\GuzzleBundleHeaderForwardPlugin\Tests\Middleware;

use EncoreLabs\Bundle\GuzzleBundleHeaderForwardPlugin\Middleware\GuzzleForwardHeaderMiddleware;
use GuzzleHttp\Psr7\Request as OutgoingRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Request as IncomingRequest;
use Symfony\Component\HttpFoundation\RequestStack;

class GuzzleForwardHeaderMiddlewareTest extends TestCase
{
    public function testConfiguredHeadersAreCopiedFromTheCurrentRequest(): void
    {
        $forwarded = $this->dispatch(['X-Request-Id', 'X-Not-Sent'], ['HTTP_X_REQUEST_ID' => 'abc123']);

        $this->assertSame('abc123', $forwarded->getHeaderLine('X-Request-Id'));
        $this->assertFalse($forwarded->hasHeader('X-Not-Sent'));
    }

    public function testHeadersOutsideTheConfiguredListAreNotForwarded(): void
    {
        $forwarded = $this->dispatch(['X-Request-Id'], ['HTTP_X_SECRET' => 'nope']);

        $this->assertFalse($forwarded->hasHeader('X-Secret'));
    }

    public function testAnEmptyRequestStackIsNotAnError(): void
    {
        $forwarded = $this->dispatch(['X-Request-Id'], null);

        $this->assertFalse($forwarded->hasHeader('X-Request-Id'));
    }

    /**
     * @param string[]             $headers configured for forwarding
     * @param array<string, string>|null $server of the current request, or null for an empty request stack
     */
    private function dispatch(array $headers, ?array $server): RequestInterface
    {
        $stack = new RequestStack();

        if (null !== $server) {
            $stack->push(IncomingRequest::create('/', 'GET', [], [], [], $server));
        }

        $forwarded = null;
        $handler = function (RequestInterface $request, array $options) use (&$forwarded) {
            $forwarded = $request;

            return 'handled';
        };

        $middleware = new GuzzleForwardHeaderMiddleware($stack, $headers);

        $this->assertSame('handled', $middleware($handler)(new OutgoingRequest('GET', 'https://example.com'), []));

        return $forwarded;
    }
}
