<?php

namespace GustRouter;

use GustRouter\Exceptions\RouteNotFoundException;
use GustRouter\Exceptions\RouteValidationException;

class Router
{
    protected array $routes = [];
    protected string $basePath = '';
    protected string $domain = '';
    protected string $defaultDomain = '';
    protected ?string $currentGroupPrefix = '';
    protected array $currentGroupMiddleware = [];
    protected array $currentGroupWheres = [];
    protected ?string $currentGroupDomain = null;
    protected array $currentDomainParameters = [];
    protected $errorHandler = null;
    protected Request $request;
    private array $container = [];

    protected bool $debug = false;
    protected bool $securityHeadersEnabled = true;
    protected array $customSecurityHeaders = [];
    protected $logger = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->defaultDomain = $this->detectCurrentDomain();
        $this->domain = $this->defaultDomain;

        // Make the current request resolvable via dependency injection so
        // handlers type-hinting Request receive the actual instance.
        $this->container[Request::class] = $request;
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

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    public function enableSecurityHeaders(bool $enabled = true): void
    {
        $this->securityHeadersEnabled = $enabled;
    }

    public function setSecurityHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $this->customSecurityHeaders[$name] = $value;
        }
    }

    public function setErrorLogger(callable $logger): void
    {
        $this->logger = $logger;
    }

    public function bind(string $abstract, $concrete): void
    {
        $this->container[$abstract] = $concrete;
    }

    public function resolve(string $abstract)
    {
        if (isset($this->container[$abstract])) {
            $concrete = $this->container[$abstract];
            return is_callable($concrete) ? $concrete($this) : $concrete;
        }

        return null;
    }

    public function domain(string $domain, \Closure $callback): void
    {
        $prev = $this->domain;
        $prevGroupDomain = $this->currentGroupDomain;
        $this->domain = rtrim($domain, '/');
        $this->currentGroupDomain = $this->domain;

        $callback($this);

        $this->domain = $prev;
        $this->currentGroupDomain = $prevGroupDomain;
    }

    public function group(array $config, \Closure $callback): void
    {
        $prevPrefix = $this->currentGroupPrefix;
        $prevMiddleware = $this->currentGroupMiddleware;
        $prevWheres = $this->currentGroupWheres;

        $prefix = $config['prefix'] ?? '';
        $middleware = $config['middleware'] ?? [];
        $wheres = $config['where'] ?? [];

        // Handle single middleware as string
        if (is_string($middleware)) {
            $middleware = [$middleware];
        } elseif (!is_array($middleware)) {
            $middleware = [];
        }

        $this->currentGroupPrefix = $this->currentGroupPrefix . (rtrim($prefix, '/') ?: '');
        $this->currentGroupMiddleware = array_merge($this->currentGroupMiddleware, $middleware);
        $this->currentGroupMiddleware = array_values(array_filter($this->currentGroupMiddleware));

        // Merge group where conditions with existing ones
        $this->currentGroupWheres = array_merge($this->currentGroupWheres, $wheres);

        $callback($this);

        $this->currentGroupPrefix = $prevPrefix;
        $this->currentGroupMiddleware = $prevMiddleware;
        $this->currentGroupWheres = $prevWheres;
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

    public function patch(string $path, $callback): Route
    {
        return $this->addRoute(['PATCH'], $path, $callback);
    }

    public function delete(string $path, $callback): Route
    {
        return $this->addRoute(['DELETE'], $path, $callback);
    }

    public function head(string $path, $callback): Route
    {
        return $this->addRoute(['HEAD'], $path, $callback);
    }

    public function options(string $path, $callback): Route
    {
        return $this->addRoute(['OPTIONS'], $path, $callback);
    }

    public function any(string $path, $callback): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], $path, $callback);
    }

    protected function addRoute(array $methods, string $path, $callback): Route
    {
        // Validate path format - check for properly formed route parameters
        // This regex checks that curly braces are properly formed and contain valid characters
        $pattern = '/\{(?:[^{}]|{[^{}]*})*\}/';
        preg_match_all($pattern, $path, $matches);

        foreach ($matches[0] as $param) {
            // Remove the outer braces to check the content
            $content = trim($param, '{}');

            // Check if the parameter has a regex pattern (e.g., {id:\d+})
            if (strpos($content, ':') !== false) {
                [$name, $regex] = explode(':', $content, 2);
                // Validate parameter name (should be alphanumeric with underscores/hyphens)
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
                    throw new RouteValidationException("Invalid parameter name in route: $path");
                }
                // The regex part is validated during route matching
            } else {
                // Validate simple parameter name (should be alphanumeric with underscores/hyphens)
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $content)) {
                    throw new RouteValidationException("Invalid parameter name in route: $path");
                }
            }
        }

        $fullPath = $this->currentGroupPrefix . $path;
        $fullPath = $fullPath ?: '/';
        $fullPath = '/' . ltrim($fullPath, '/'); // Ensure leading slash, avoid double slashes

        // Remove trailing slash except for root path
        if ($fullPath !== '/') {
            $fullPath = rtrim($fullPath, '/');
        }
        $route = new Route($fullPath, $callback, $methods);

        // Add group middleware to route
        foreach ($this->currentGroupMiddleware as $middleware) {
            $route->middleware($middleware);
        }

        // Add group where conditions to route
        foreach ($this->currentGroupWheres as $param => $pattern) {
            $route->where($param, $pattern);
        }

        // Apply group domain to route if set, otherwise use default domain
        if ($this->currentGroupDomain) {
            $route->domain = $this->currentGroupDomain;
        } else {
            $route->domain = $this->defaultDomain;
        }

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
            if ($route->routeName !== $name) {
                continue;
            }

            $wheres = $route->getWheres();
            $usedKeys = [];

            $path = preg_replace_callback('/\{([^}]*)\}/', function ($m) use ($params, $wheres, $name, &$usedKeys) {
                $definition = $m[1];

                if (strpos($definition, ':') !== false) {
                    [$param, $regex] = explode(':', $definition, 2);
                } else {
                    $param = $definition;
                    $regex = $wheres[$param] ?? null;
                }

                if (!array_key_exists($param, $params)) {
                    throw new RouteValidationException("Missing required parameter '$param' for route '$name'.");
                }

                $value = (string) $params[$param];

                if ($regex !== null && $regex !== '') {
                    if (!$this->isValidRegex($regex)) {
                        throw new RouteValidationException("Invalid regex pattern for parameter '$param'.");
                    }
                    if (!preg_match($this->compileRegex('^' . $regex . '$'), $value)) {
                        throw new RouteValidationException("Parameter '$param' does not match the required pattern for route '$name'.");
                    }
                }

                $usedKeys[$param] = true;
                return rawurlencode($value);
            }, $route->path);

            $query = array_diff_key($params, $usedKeys);
            $queryString = empty($query) ? '' : '?' . http_build_query($query);

            return ($route->domain ?? $this->defaultDomain) . $this->basePath . $path . $queryString;
        }

        throw new RouteNotFoundException("Route with name '$name' not found.");
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    protected function routeAllowsMethod(Route $route): bool
    {
        $method = $this->request->method;

        if (in_array($method, $route->methods, true)) {
            return true;
        }

        // HEAD requests fall back to matching GET routes (RFC 9110).
        return $method === 'HEAD' && in_array('GET', $route->methods, true);
    }

    protected function matches(Route $route): bool
    {
        if (!$this->routeAllowsMethod($route)) {
            return false;
        }

        return $this->matchesPath($route);
    }

    protected function matchesPath(Route $route): bool
    {
        // Check domain/subdomain matching and get domain parameters
        $domainParams = [];
        if ($route->domain && !$this->matchesDomain($route->domain, $domainParams)) {
            return false;
        }

        $parameterNames = [];
        $parameterRegexes = [];

        // Get route's where conditions
        $whereConditions = $route->getWheres();

        $segments = preg_split('/(\{[^}]*\})/', $route->path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $pattern = '';

        foreach ($segments as $segment) {
            if (strlen($segment) > 1 && $segment[0] === '{' && substr($segment, -1) === '}') {
                $paramDefinition = substr($segment, 1, -1);

                if (strpos($paramDefinition, ':') !== false) {
                    // Format: {param:regex} - legacy format
                    [$param, $regex] = explode(':', $paramDefinition, 2);

                    if (!$this->isValidRegex($regex)) {
                        throw new RouteValidationException("Invalid regex pattern: $regex");
                    }
                } else {
                    $param = $paramDefinition;
                    $regex = $whereConditions[$param] ?? '[^/]+';

                    if (isset($whereConditions[$param]) && !$this->isValidRegex($regex)) {
                        throw new RouteValidationException("Invalid regex pattern in where condition: $regex");
                    }
                }

                $parameterNames[] = $param;
                $parameterRegexes[$param] = $regex;
                $pattern .= '(' . str_replace('#', '\\#', $regex) . ')';
            } else {
                // Literal path segment - escape regex metacharacters
                $pattern .= preg_quote($segment, '#');
            }
        }

        // Escape base path and anchor the pattern
        $basePathEscaped = preg_quote($this->basePath, '#');
        $pattern = '#^' . $basePathEscaped . $pattern . '/?$#';

        if (!preg_match($pattern, $this->basePath . $this->request->path, $matches)) {
            return false;
        }

        array_shift($matches);

        // Assign parameters with their names, validating them
        $route->parameters = [];
        foreach ($parameterNames as $index => $name) {
            if (!isset($matches[$index])) {
                continue;
            }

            $value = $matches[$index];

            // Validate the parameter against its regex
            if (!preg_match($this->compileRegex('^' . $parameterRegexes[$name] . '$'), $value)) {
                return false; // Parameter doesn't match its defined pattern
            }

            $route->parameters[$name] = $value;
        }

        // Add domain parameters to route parameters
        $route->parameters = array_merge($route->parameters, $domainParams);

        return true;
    }

    private function matchesDomain(string $routeDomain, array &$domainParams = []): bool
    {
        $currentHost = parse_url($this->detectCurrentDomain(), PHP_URL_HOST);
        $routeHost = parse_url($routeDomain, PHP_URL_HOST);

        if (!$currentHost || !$routeHost) {
            return false;
        }

        // Check if the route domain contains parameters (e.g., {sub}.example.com)
        if (strpos($routeHost, '{') !== false && strpos($routeHost, '}') !== false) {
            // Build the regex pattern step by step to properly handle both literal dots and parameter patterns
            $pattern = '';
            $lastPos = 0;
            $paramNames = [];

            // Find all parameter placeholders with their positions
            preg_match_all('/\{([^}]+)\}/', $routeHost, $matches, PREG_OFFSET_CAPTURE);

            // Process each parameter and the literal text around it
            foreach ($matches[0] as $i => $match) {
                $fullMatch = $match[0];
                $matchOffset = $match[1];

                // Add the literal text before this parameter (properly escaped)
                $literalText = substr($routeHost, $lastPos, $matchOffset - $lastPos);
                $pattern .= preg_quote($literalText, '/');

                // Process the parameter definition
                $paramDefinition = $matches[1][$i][0];

                // Check if the parameter has a regex pattern (e.g., {sub:\w+})
                if (strpos($paramDefinition, ':') !== false) {
                    [$param, $regex] = explode(':', $paramDefinition, 2);
                    if (!$this->isValidRegex($regex)) {
                        return false;
                    }
                    $regexPattern = $regex;
                    $paramNames[] = $param; // Store the clean parameter name
                } else {
                    // Default pattern for subdomain parameters
                    $regexPattern = '[^.]+';
                    $paramNames[] = $paramDefinition; // Store the parameter name
                }

                // Add the regex pattern for the parameter (this goes inside parentheses for capture)
                $pattern .= '(' . $regexPattern . ')';

                // Update the position to after this parameter
                $lastPos = $matchOffset + strlen($fullMatch);
            }

            // Add any remaining literal text after the last parameter
            if ($lastPos < strlen($routeHost)) {
                $remainingText = substr($routeHost, $lastPos);
                $pattern .= preg_quote($remainingText, '/');
            }

            // Create the full regex pattern
            $pattern = "/^" . $pattern . "$/";

            if (@preg_match($pattern, $currentHost, $domainMatches)) {
                // Extract matched parameters (first element is the full match, so skip it)
                array_shift($domainMatches); // Remove full match

                // Add matched values to domain parameters
                foreach ($paramNames as $index => $paramName) {
                    if (isset($domainMatches[$index])) {
                        $domainParams[$paramName] = $domainMatches[$index];
                    }
                }

                return true;
            }

            return false;
        }

        // Simple domain comparison
        return $currentHost === $routeHost;
    }

    /**
     * Build a delimited regex, escaping the chosen delimiter inside the pattern.
     */
    private function compileRegex(string $regex): string
    {
        return '#' . str_replace('#', '\\#', $regex) . '#';
    }

    private function isValidRegex(string $regex): bool
    {
        if ($regex === '' || strlen($regex) > 512) {
            return false;
        }

        // Reject control characters
        if (preg_match('/[\x00-\x1f\x7f]/', $regex)) {
            return false;
        }

        // Reject nested quantifiers which can trigger catastrophic backtracking (ReDoS)
        if (preg_match('/([+*]|\{\d+(?:,\d*)?\})\s*[)\]]?\s*([+*]|\{\d+(?:,\d*)?\})/', $regex)) {
            return false;
        }

        // Attempt to compile the regex to check validity
        $result = @preg_match($this->compileRegex($regex), '');
        return $result !== false && preg_last_error() === PREG_NO_ERROR;
    }

    protected function executeMiddlewareStack(Route $route, Request $request)
    {
        $middlewareStack = $route->getMiddlewareStack();

        if (empty($middlewareStack)) {
            // No middleware to execute, run the route directly
            return $this->executeRoute($route);
        }

        // Create the final handler that runs the route if all middleware passes
        $finalHandler = function ($req) use ($route) {
            return $this->executeRoute($route);
        };

        // Build the middleware chain from the end to the beginning
        $next = $finalHandler;

        // Process middleware in reverse order to build the chain properly
        for ($i = count($middlewareStack) - 1; $i >= 0; $i--) {
            $middlewareClass = $middlewareStack[$i];
            $next = function ($req) use ($middlewareClass, $next) {
                // Prefer a container binding (allows DI/configuration), then
                // fall back to instantiating the middleware class directly.
                $middleware = $this->resolve($middlewareClass);

                if ($middleware === null && class_exists($middlewareClass)) {
                    $middleware = $this->instantiateClass($middlewareClass);
                }

                if (is_object($middleware) && method_exists($middleware, 'handle')) {
                    return $middleware->handle($req, $next);
                }

                // If middleware cannot be resolved, continue the chain.
                return $next($req);
            };
        }

        // Execute the middleware chain
        return $next($request);
    }

    private function executeRoute(Route $route)
    {
        $callback = $route->callback;
        $parameters = $route->parameters ?? [];

        if (is_array($callback)) {
            // Check if the controller class exists and the method exists
            if (!is_string($callback[0]) || !class_exists($callback[0])) {
                throw new \InvalidArgumentException("Controller class does not exist: " . (is_string($callback[0]) ? $callback[0] : gettype($callback[0])));
            }
            if (!method_exists($callback[0], $callback[1])) {
                throw new \InvalidArgumentException("Controller method does not exist: {$callback[0]}::{$callback[1]}");
            }

            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }

        $args = $this->buildArguments($reflection->getParameters(), $parameters);

        if (is_array($callback)) {
            $controller = $this->instantiateClass($callback[0]);
            $method = $callback[1];
            return $controller->$method(...$args);
        }

        return $callback(...$args);
    }

    /**
     * Build the argument list for a route callback, resolving typed
     * dependencies from the container and mapping route parameters by name.
     */
    private function buildArguments(array $reflectionParameters, array $parameters): array
    {
        $remaining = $parameters;
        $args = [];

        foreach ($reflectionParameters as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->resolveDependency($type->getName());
                continue;
            }

            $name = $param->getName();

            if (array_key_exists($name, $remaining)) {
                $args[] = $remaining[$name];
                unset($remaining[$name]);
            } elseif (!empty($remaining)) {
                $args[] = array_shift($remaining);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return $args;
    }

    private function resolveDependency(string $className)
    {
        $resolved = $this->resolve($className);

        if ($resolved !== null) {
            return $resolved;
        }

        return $this->instantiateClass($className);
    }

    protected function renderResponse($result): void
    {
        if ($result instanceof Response) {
            $result->send($this->request->method !== 'HEAD');
            return;
        }

        // HEAD responses must not include a body.
        if ($this->request->method === 'HEAD') {
            return;
        }

        if (is_array($result)) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } else {
            // The result should already be sanitized by the Request class or the handler
            echo (string) $result;
        }
    }

    protected function applySecurityHeaders(): void
    {
        if (!$this->securityHeadersEnabled || headers_sent()) {
            return;
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        $headers = array_merge($headers, $this->customSecurityHeaders);

        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
    }

    public function run(): void
    {
        $this->applySecurityHeaders();

        try {
            $matchedRoute = null;

            foreach ($this->routes as $route) {
                if ($this->matches($route)) {
                    $matchedRoute = $route;
                    break;
                }
            }

            if ($matchedRoute) {
                // Execute middleware stack which will also execute the route
                $result = $this->executeMiddlewareStack($matchedRoute, $this->request);

                // If the result is null or false, it means middleware handled the response
                if ($result !== null && $result !== false) {
                    $this->renderResponse($result);
                }
                return;
            }

            // No route matched: determine whether the path exists with another method (405)
            $allowed = [];
            foreach ($this->routes as $route) {
                if ($this->routeAllowsMethod($route)) {
                    continue;
                }
                if ($this->matchesPath($route)) {
                    foreach ($route->methods as $method) {
                        $allowed[$method] = true;
                    }
                    if (in_array('GET', $route->methods, true)) {
                        $allowed['HEAD'] = true;
                    }
                }
            }

            if (!empty($allowed)) {
                http_response_code(405);
                if (!headers_sent()) {
                    header('Allow: ' . implode(', ', array_keys($allowed)));
                }
                $errorResponse = $this->errorHandler ? call_user_func($this->errorHandler, 405) : 'Method Not Allowed';
                $this->renderResponse($errorResponse);
                return;
            }

            http_response_code(404);
            $errorResponse = $this->errorHandler ? call_user_func($this->errorHandler, 404) : 'Route not found';
            $this->renderResponse($errorResponse);
        } catch (RouteNotFoundException $e) {
            http_response_code(404);
            $errorResponse = $this->errorHandler ? call_user_func($this->errorHandler, 404) : $e->getMessage();
            $this->renderResponse($errorResponse);
        } catch (RouteValidationException $e) {
            http_response_code(400);
            $message = $this->debug ? $e->getMessage() : 'Bad Request';
            $errorResponse = $this->errorHandler ? call_user_func($this->errorHandler, 400) : $message;
            $this->renderResponse($errorResponse);
        } catch (\Throwable $e) {
            http_response_code(500);
            // Never leak internal details to the client unless debug mode is enabled.
            if ($this->logger !== null) {
                call_user_func($this->logger, $e);
            }
            $errorMessage = $this->debug ? ('Server Error - ' . $e->getMessage()) : 'Server Error';
            $errorResponse = $this->errorHandler ? call_user_func($this->errorHandler, 500) : $errorMessage;
            $this->renderResponse($errorResponse);
        }
    }

    private function instantiateClass(string $className, array $stack = [])
    {
        if (!class_exists($className)) {
            throw new \RuntimeException("Class $className does not exist");
        }

        if (in_array($className, $stack, true)) {
            throw new \RuntimeException("Circular dependency detected while resolving $className");
        }

        $reflection = new \ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            $kind = $reflection->isInterface() ? 'interface' : ($reflection->isAbstract() ? 'abstract class' : 'non-instantiable class');
            throw new \RuntimeException("Cannot instantiate $kind $className");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            // No constructor, create instance directly
            return $reflection->newInstance();
        }

        $stack[] = $className;

        // Get constructor parameters
        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $paramClass = $type->getName();

                // Try to resolve from container
                $resolved = $this->resolve($paramClass);
                if ($resolved !== null) {
                    $params[] = $resolved;
                } else {
                    // Recursively instantiate dependencies
                    $params[] = $this->instantiateClass($paramClass, $stack);
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Cannot instantiate class $className: parameter \${$param->getName()} has no default value and is not a class");
            }
        }

        return $reflection->newInstanceArgs($params);
    }
}
