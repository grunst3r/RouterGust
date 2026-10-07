<?php

namespace GustRouter\Tests;

use GustRouter\Request;
use GustRouter\Response;
use GustRouter\Router;
use PHPUnit\Framework\TestCase;

class TokenAuthMiddleware
{
    public function handle($request, $next)
    {
        $header = (string) $request->header('Authorization', '');

        if (!preg_match('/^Bearer\s+(\S+)$/', $header, $matches) || $matches[1] !== 'valid-token') {
            return new Response(json_encode(['error' => 'Unauthorized']), 401);
        }

        return $next($request);
    }
}

class ApiCorsMiddleware
{
    public function handle($request, $next)
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new Response('', 204);
        }

        return $next($request);
    }
}

class WrapMiddleware
{
    private string $tag;

    public function __construct(string $tag = 'W')
    {
        $this->tag = $tag;
    }

    public function handle($request, $next)
    {
        return '[' . $this->tag . ']' . $next($request) . '[/' . $this->tag . ']';
    }
}

class ApiTest extends TestCase
{
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

    public function testBearerTokenIsReadFromAuthorizationHeader(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123';

        $request = new Request();

        $this->assertSame('Bearer abc123', $request->header('Authorization'));
        $this->assertSame('Bearer abc123', $request->header('authorization'));

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testCustomApiHeadersAreAccessible(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_API_KEY'] = 'secret-key';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request();

        $this->assertSame('secret-key', $request->header('X-Api-Key'));
        $this->assertSame('application/json', $request->header('Accept'));

        unset($_SERVER['HTTP_X_API_KEY'], $_SERVER['HTTP_ACCEPT']);
    }

    public function testApiRejectsRequestWithoutToken(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/profile';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $router = new Router(new Request());
        $router->get('/api/profile', fn() => ['user' => 'me'])
            ->middleware(TokenAuthMiddleware::class);

        $output = $this->runAndCapture($router);

        $this->assertSame('{"error":"Unauthorized"}', $output);
        $this->assertSame(401, http_response_code());
    }

    public function testApiRejectsRequestWithInvalidToken(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/profile';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong-token';

        $router = new Router(new Request());
        $router->get('/api/profile', fn() => ['user' => 'me'])
            ->middleware(TokenAuthMiddleware::class);

        $output = $this->runAndCapture($router);

        $this->assertSame('{"error":"Unauthorized"}', $output);
        $this->assertSame(401, http_response_code());

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testApiAllowsRequestWithValidToken(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/profile';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token';

        $router = new Router(new Request());
        $router->get('/api/profile', fn() => ['user' => 'me'])
            ->middleware(TokenAuthMiddleware::class);

        $output = $this->runAndCapture($router);

        $this->assertSame(['user' => 'me'], json_decode($output, true));
        $this->assertSame(200, http_response_code());

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testPublicApiRouteDoesNotRequireToken(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/health';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $router = new Router(new Request());
        $router->get('/api/health', fn() => ['status' => 'ok']);

        $output = $this->runAndCapture($router);

        $this->assertSame(['status' => 'ok'], json_decode($output, true));
    }

    public function testJsonBodyIsParsedAndSanitized(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/echo';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = new Request();
        $request->setBodyForTesting('{"name":"<script>alert(1)</script>","age":30}');

        $router = new Router($request);
        $router->post('/api/echo', function (Request $r) {
            $body = $r->getJson();
            return ['name' => $body['name'], 'age' => $body['age']];
        });

        $output = $this->runAndCapture($router);
        $decoded = json_decode($output, true);

        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $decoded['name']);
        $this->assertSame(30, $decoded['age']);
    }

    public function testRawJsonIsNotSanitized(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/raw';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = new Request();
        $request->setBodyForTesting('{"name":"<b>bold</b>"}');

        $router = new Router($request);
        $router->post('/api/raw', fn(Request $r) => ['name' => $r->json()['name']]);

        $decoded = json_decode($this->runAndCapture($router), true);

        $this->assertSame('<b>bold</b>', $decoded['name']);
    }

    public function testCorsPreflightReturns204WithoutBody(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/data';
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

        $router = new Router(new Request());
        $router->options('/api/data', fn() => 'should not run')
            ->middleware(ApiCorsMiddleware::class);

        $output = $this->runAndCapture($router);

        $this->assertSame('', $output);
        $this->assertSame(204, http_response_code());
    }

    public function testApiReturnsJsonForArrayPayload(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/users';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->get('/api/users', fn() => [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ]);

        $output = $this->runAndCapture($router);

        $this->assertJson($output);
        $this->assertCount(2, json_decode($output, true));
    }

    public function testApiRouteParameterValidation(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/users/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->get('/api/users/{id:\d+}', fn($id) => ['id' => (int) $id])->name('api.user');

        $output = $this->runAndCapture($router);

        $this->assertSame(['id' => 123], json_decode($output, true));
    }

    public function testApiRouteWithInvalidParameterReturns404(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/users/abc';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->get('/api/users/{id:\d+}', fn($id) => ['id' => $id]);

        $output = $this->runAndCapture($router);

        $this->assertSame('Route not found', $output);
        $this->assertSame(404, http_response_code());
    }

    public function testApiMiddlewareChainOrder(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/chain';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->get('/api/chain', fn() => 'ok')
            ->middleware(WrapMiddleware::class)
            ->middleware(WrapMiddleware::class);

        $output = $this->runAndCapture($router);

        $this->assertSame('[W][W]ok[/W][/W]', $output);
    }

    public function testQueryStringAppendedToGeneratedApiUrl(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router(new Request());
        $router->get('/api/search/{term}', fn($term) => $term)->name('api.search');

        $url = $router->url('api.search', ['term' => 'php', 'page' => 2]);

        $this->assertStringEndsWith('/api/search/php?page=2', $url);
    }
}
