<?php

namespace GustRouter;

class Router
{
    private array $routes = [];
    private string $currentGroupPrefix = '';
    private ?string $currentGroupMiddleware = null;
    private $notFoundHandler;
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function addRoute(string $method, string $path, $handler): Route
    {
        $route = new Route(
            $this->currentGroupPrefix . $path,
            $handler,
            [$method]
        );

        if ($this->currentGroupMiddleware) {
            $route->middleware($this->currentGroupMiddleware);
        }

        $this->routes[] = $route;
        return $route;
    }

    public function get(string $path, $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function any(string $path, $handler): Route
    {
        return $this->addRoute('ANY', $path, $handler);
    }

    public function group(array $attributes, \Closure $callback): void
    {
        $prevPrefix = $this->currentGroupPrefix;
        $prevMiddleware = $this->currentGroupMiddleware;

        if (isset($attributes['prefix'])) {
            $this->currentGroupPrefix .= $attributes['prefix'];
        }

        if (isset($attributes['middleware'])) {
            $this->currentGroupMiddleware = $attributes['middleware'];
        }

        $callback($this);

        $this->currentGroupPrefix = $prevPrefix;
        $this->currentGroupMiddleware = $prevMiddleware;
    }

    private function matches(Route $route): bool
    {
        $path = rtrim($this->request->getPath(), '/');
        $routePath = rtrim($route->path, '/');
        $method = $this->request->getMethod();

        if (!in_array($method, $route->methods) && !in_array('ANY', $route->methods)) {
            return false;
        }

        return $path === $routePath;
    }

    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function getRoutes(): array {
        return $this->routes;
    }

    public function run(): void
    {
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
            
            if (is_array($callback)) {
                $controller = new $callback[0];
                $method = $callback[1];
                echo $controller->$method(...array_values($parameters));
            } else {
                echo $callback(...array_values($parameters));
            }
            return;
        }

        // Si no se encontró ninguna ruta
        if ($this->notFoundHandler) {
            http_response_code(404);
            echo call_user_func($this->notFoundHandler, $this->request);
        } else {
            Response::abort(404, 'Ruta no encontrada');
        }
    }
}
