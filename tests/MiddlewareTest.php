<?php

namespace GustRouter\Tests;

use GustRouter\Request;
use GustRouter\Response;
use GustRouter\Router;
use PHPUnit\Framework\TestCase;

class RecordingMiddleware
{
    public static array $calls = [];

    public function handle($request, $next)
    {
        self::$calls[] = 'before';
        $result = $next($request);
        self::$calls[] = 'after';
        return $result;
    }
}

class MiddlewareA
{
    public function handle($request, $next)
    {
        return '[A]' . $next($request) . '[/A]';
    }
}

class MiddlewareB
{
    public function handle($request, $next)
    {
        return '[B]' . $next($request) . '[/B]';
    }
}

class AbortMiddleware
{
    public function handle($request, $next)
    {
        return new Response('aborted', 403);
    }
}

class HaltMiddleware
{
    public function handle($request, $next)
    {
        return 'halted';
    }
}

class ContainerMiddleware
{
    private string $marker;

    public function __construct(string $marker = 'container')
    {
        $this->marker = $marker;
    }

    public function handle($request, $next)
    {
        return '[' . $this->marker . ']' . $next($request);
    }
}

class RequestCaptureMiddleware
{
    public static $captured = null;

    public function handle($request, $next)
    {
        self::$captured = $request;
        return $next($request);
    }
}

class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        RecordingMiddleware::$calls = [];
        RequestCaptureMiddleware::$captured = null;
    }

    private function runAndCapture(Router $router): string
    {
        http_response_code(200);
        ob_start();
        try {
            $router->run();
        } finally {
            $output = ob_get_clean();
        }
        return $output;
    }

    public function testMiddlewareRunsBeforeAndAfterRoute(): void
    {
        $_SERVER['REQUEST_URI'] = '/mw';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->get('/mw', fn() => 'body')->middleware(RecordingMiddleware::class);

        $output = $this->runAndCapture($router);

        $this->assertSame('body', $output);
        $this->assertSame(['before', 'after'], RecordingMiddleware::$calls);
    }

    public function testMiddlewareChainOrder(): void
    {
        $_SERVER['REQUEST_URI'] = '/chain';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->get('/chain', fn() => 'body')
            ->middleware(MiddlewareA::class)
            ->middleware(MiddlewareB::class);

        $this->assertSame('[A][B]body[/B][/A]', $this->runAndCapture($router));
    }

    public function testMiddlewareCanShortCircuitWithResponse(): void
    {
        $_SERVER['REQUEST_URI'] = '/blocked';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $executed = false;
        $router = new Router(new Request());
        $router->get('/blocked', function () use (&$executed) {
            $executed = true;
            return 'never';
        })->middleware(AbortMiddleware::class);

        $output = $this->runAndCapture($router);

        $this->assertSame('aborted', $output);
        $this->assertSame(403, http_response_code());
        $this->assertFalse($executed);
    }

    public function testMiddlewareCanHaltWithoutCallingNext(): void
    {
        $_SERVER['REQUEST_URI'] = '/halt';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $executed = false;
        $router = new Router(new Request());
        $router->get('/halt', function () use (&$executed) {
            $executed = true;
            return 'never';
        })->middleware(HaltMiddleware::class);

        $output = $this->runAndCapture($router);

        $this->assertSame('halted', $output);
        $this->assertFalse($executed);
    }

    public function testMiddlewareResolvedFromContainerByClass(): void
    {
        $_SERVER['REQUEST_URI'] = '/container';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->bind(ContainerMiddleware::class, fn() => new ContainerMiddleware('bound'));
        $router->get('/container', fn() => 'body')->middleware(ContainerMiddleware::class);

        $this->assertSame('[bound]body', $this->runAndCapture($router));
    }

    public function testMiddlewareResolvedFromContainerByAlias(): void
    {
        $_SERVER['REQUEST_URI'] = '/alias';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->bind('my.middleware', fn() => new ContainerMiddleware('alias'));
        $router->get('/alias', fn() => 'body')->middleware('my.middleware');

        $this->assertSame('[alias]body', $this->runAndCapture($router));
    }

    public function testMiddlewareReceivesTheActualRequestInstance(): void
    {
        $_SERVER['REQUEST_URI'] = '/capture';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $request = new Request();
        $router = new Router($request);
        $router->get('/capture', fn() => 'ok')->middleware(RequestCaptureMiddleware::class);

        $this->runAndCapture($router);

        $this->assertSame($request, RequestCaptureMiddleware::$captured);
    }

    public function testGroupMiddlewareAppliesToAllRoutes(): void
    {
        $_SERVER['REQUEST_URI'] = '/group/two';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->group(['prefix' => '/group', 'middleware' => RecordingMiddleware::class], function ($r) {
            $r->get('/one', fn() => 'one');
            $r->get('/two', fn() => 'two');
        });

        $this->assertSame('two', $this->runAndCapture($router));
        $this->assertSame(['before', 'after'], RecordingMiddleware::$calls);
    }

    public function testGroupAndRouteMiddlewareOrdering(): void
    {
        $_SERVER['REQUEST_URI'] = '/ordered';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->group(['middleware' => MiddlewareA::class], function ($r) {
            $r->get('/ordered', fn() => 'body')->middleware(MiddlewareB::class);
        });

        $this->assertSame('[A][B]body[/B][/A]', $this->runAndCapture($router));
    }

    public function testMultipleGroupMiddlewarePreservesOrder(): void
    {
        $_SERVER['REQUEST_URI'] = '/multi';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->group(['middleware' => [MiddlewareA::class, MiddlewareB::class]], function ($r) {
            $r->get('/multi', fn() => 'body');
        });

        $this->assertSame('[A][B]body[/B][/A]', $this->runAndCapture($router));
    }

    public function testMiddlewareNotRunForUnmatchedRoute(): void
    {
        $_SERVER['REQUEST_URI'] = '/other';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->get('/only', fn() => 'body')->middleware(RecordingMiddleware::class);

        $this->runAndCapture($router);

        $this->assertSame([], RecordingMiddleware::$calls);
    }
}
