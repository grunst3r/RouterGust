<?php

namespace GustRouter;

class Router
{
    protected array $routes = [];
    protected string $basePath = '';
    protected string $domain = '';
    protected string $defaultDomain = '';
    protected ?string $currentGroupPrefix = '';
    protected ?string $currentGroupMiddleware = null;
    protected ?string $currentGroupDomain = null;
    protected $errorHandler = null;
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->defaultDomain = $this->detectCurrentDomain();
        $this->domain = $this->defaultDomain;
    }

    public function detectCurrentDomain(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    public function setBasePath(string $path): void
    {
        $this->basePath = rtrim($path, '/');
    }

    public function setDomain(string $domain): void
    {
        $this->domain = rtrim($domain, '/');
        $this->defaultDomain = $this->domain;
    }

    public function domain(string $domain, \Closure $callback): void
    {
        $prev = $this->domain;
        $this->domain = rtrim($domain, '/');
        $this->currentGroupDomain = $this->domain;

        $callback($this);

        $this->domain = $prev;
        $this->currentGroupDomain = null;
    }

    public function group(array $config, \Closure $callback): void
    {
        $prevPrefix = $this->currentGroupPrefix;
        $prevMiddleware = $this->currentGroupMiddleware;

        $this->currentGroupPrefix .= rtrim($config['prefix'] ?? '', '/');
        $this->currentGroupMiddleware = $config['middleware'] ?? null;

        $callback($this);

        $this->currentGroupPrefix = $prevPrefix;
        $this->currentGroupMiddleware = $prevMiddleware;
    }

    public function get(string $path, $callback): Route
    {
        return $this->addRoute(['GET'], $path, $callback);
    }

    public function post(string $path, $callback): Route
    {
        return $this->addRoute(['POST'], $path, $callback);
    }

    public function put(string $path, $callback): Route
    {
        return $this->addRoute(['PUT'], $path, $callback);
    }

    public function any(string $path, $callback): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $path, $callback);
    }

    protected function addRoute(array $methods, string $path, $callback): Route
    {
        $fullPath = rtrim(($this->currentGroupPrefix ?: '') . $path, '/');
        $fullPath = $fullPath ?: '/';
        $route = new Route($fullPath, $callback, $methods);

        if ($this->currentGroupMiddleware) {
            $route->middleware($this->currentGroupMiddleware);
        }

        $route->domain = $this->currentGroupDomain ?? $this->defaultDomain;
        $this->routes[] = $route;

        return $route;
    }

    public function setErrorHandler(callable $handler): void
    {
        $this->errorHandler = $handler;
    }

    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->routeName === $name) {
                $url = $route->path;
                $usedKeys = [];
                foreach ($params as $key => $value) {
                    if (strpos($url, '{' . $key . '}') !== false) {
                        $url = str_replace('{' . $key . '}', $value, $url);
                        $usedKeys[] = $key;
                    }
                }
                $query = array_diff_key($params, array_flip($usedKeys));
                $queryString = empty($query) ? '' : '?' . http_build_query($query);

                return ($route->domain ?? $this->defaultDomain) . $this->basePath . $url . $queryString;
            }
        }
        throw new \Exception("Ruta con nombre '$name' no encontrada.");
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    protected function matches(Route $route): bool
    {
        if (!in_array($this->request->method, $route->methods)) {
            return false;
        }

        if ($route->domain && parse_url($this->detectCurrentDomain(), PHP_URL_HOST) !== parse_url($route->domain, PHP_URL_HOST)) {
            return false;
        }

        $pattern = preg_replace('#\{([^}]+)\}#', '([^/]+)', $route->path);
        $pattern = "#^" . $this->basePath . $pattern . "/?$#";

        if (preg_match($pattern, $this->basePath . $this->request->path, $matches)) {
            array_shift($matches);
            $route->parameters = $matches;
            return true;
        }

        return false;
    }

    protected function renderResponse($result): void
    {
        if (is_array($result)) {
            header('Content-Type: application/json');
            echo json_encode($result);
        } else {
            echo $result;
        }
    }

    public function run(): void
    {
        try {
            $matchedRoute = null;

            foreach ($this->routes as $route) {
                if ($this->matches($route)) {
                    $matchedRoute = $route;
                    break;
                }
            }

            if ($matchedRoute) {
                if ($matchedRoute->middleware) {
                    $middleware = new $matchedRoute->middleware;
                    $middleware->handle($this->request);
                }

                $callback = $matchedRoute->callback;
                $parameters = $matchedRoute->parameters ?? [];

                $reflection = is_array($callback)
                    ? new \ReflectionMethod($callback[0], $callback[1])
                    : new \ReflectionFunction($callback);

                $args = [];

                foreach ($reflection->getParameters() as $param) {
                    $type = $param->getType();
                    if ($type && !$type->isBuiltin()) {
                        $className = $type->getName();
                        $args[] = new $className; // Crea instancia automáticamente
                    }
                }

                $args = array_merge($args, $parameters);

                if (is_array($callback)) {
                    $controller = new $callback[0];
                    $method = $callback[1];
                    $result = $controller->$method(...$args);
                    $this->renderResponse($result);
                } else {
                    $result = $callback(...$args);
                    $this->renderResponse($result);
                }
                return;
            }

            http_response_code(404);
            echo $this->errorHandler ? call_user_func($this->errorHandler, 404) : 'Ruta no encontrada';
        } catch (\Throwable $e) {
            http_response_code(500);
            echo $this->errorHandler ? call_user_func($this->errorHandler, 500) : 'Error del servidor' . ' - ' . $e->getMessage();
        }
    }
}
