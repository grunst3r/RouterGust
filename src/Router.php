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
        $this->currentGroupMiddleware = array_filter($this->currentGroupMiddleware);
        
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
            if ($route->routeName === $name) {
                $url = $route->path;
                $usedKeys = [];
                
                // Validate and replace route parameters
                foreach ($params as $key => $value) {
                    if (strpos($url, '{' . $key . '}') !== false) {
                        // Sanitize the value before inserting
                        $sanitizedValue = $this->sanitizeUrlParam($value);
                        $url = str_replace('{' . $key . '}', $sanitizedValue, $url);
                        $usedKeys[] = $key;
                    }
                }
                
                $query = array_diff_key($params, array_flip($usedKeys));
                $queryString = empty($query) ? '' : '?' . http_build_query($query);

                return ($route->domain ?? $this->defaultDomain) . $this->basePath . $url . $queryString;
            }
        }
        throw new RouteNotFoundException("Route with name '$name' not found.");
    }

    private function sanitizeUrlParam($param): string
    {
        // Sanitize URL parameters to prevent injection
        return urlencode((string) $param);
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

        // Check domain/subdomain matching and get domain parameters
        $domainParams = [];
        if ($route->domain && !$this->matchesDomain($route->domain, $domainParams)) {
            return false;
        }

        $parameterNames = [];
        $parameterRegexes = [];

        // Get route's where conditions
        $whereConditions = $route->getWheres();

        // Improved regex pattern matching with validation
        $pattern = preg_replace_callback('/\{([^}]+)\}/', function ($matches) use (&$parameterNames, &$parameterRegexes, $whereConditions) {
            $paramDefinition = $matches[1];
            
            if (strpos($paramDefinition, ':') !== false) {
                // Format: {param:regex} - legacy format
                [$param, $regex] = explode(':', $paramDefinition, 2);
                
                // Validate the regex to prevent injection
                if (!$this->isValidRegex($regex)) {
                    throw new RouteValidationException("Invalid regex pattern: $regex");
                }
                
                $parameterNames[] = $param;
                $parameterRegexes[$param] = $regex;
                return '(' . $regex . ')';
            } else {
                // Format: {param} - check for where conditions
                $parameterNames[] = $paramDefinition;
                
                // Check if this parameter has a where condition
                if (isset($whereConditions[$paramDefinition])) {
                    $regex = $whereConditions[$paramDefinition];
                    
                    // Validate the regex to prevent injection
                    if (!$this->isValidRegex($regex)) {
                        throw new RouteValidationException("Invalid regex pattern in where condition: $regex");
                    }
                    
                    $parameterRegexes[$paramDefinition] = $regex;
                    return '(' . $regex . ')';
                } else {
                    // Default regex for parameter without where condition
                    $parameterRegexes[$paramDefinition] = '[^\/]+';
                    return '([^\/]+)';
                }
            }
        }, $route->path);

        // Escape special regex characters in the path
        $basePathEscaped = preg_quote($this->basePath, '/');
        $pattern = "#^" . $basePathEscaped . $pattern . "/?$#";

        if (preg_match($pattern, $this->basePath . $this->request->path, $matches)) {
            array_shift($matches);

            // Assign parameters with their names, validating them
            $route->parameters = [];
            foreach ($parameterNames as $index => $name) {
                if (isset($matches[$index])) {
                    $value = $matches[$index];
                    
                    // Validate the parameter against its regex if defined
                    if (isset($parameterRegexes[$name])) {
                        $paramRegex = $parameterRegexes[$name];
                        if (!preg_match("/^$paramRegex$/", $value)) {
                            return false; // Parameter doesn't match its defined pattern
                        }
                    }
                    
                    $route->parameters[$name] = $value;
                }
            }
            
            // Add domain parameters to route parameters
            $route->parameters = array_merge($route->parameters, $domainParams);
            
            return true;
        }

        return false;
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
            
            if (preg_match($pattern, $currentHost, $domainMatches)) {
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

    private function isValidRegex(string $regex): bool
    {
        // Basic validation to prevent dangerous regex patterns
        // Check for potentially dangerous characters/sequences
        if (preg_match('/[\x00-\x1f\x7f]/', $regex)) {
            return false; // Contains control characters
        }
        
        // Attempt to compile the regex to check validity
        $result = @preg_match("/$regex/", '');
        return $result !== false;
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
                if (class_exists($middlewareClass)) {
                    $middleware = new $middlewareClass();
                    
                    if (method_exists($middleware, 'handle')) {
                        return $middleware->handle($req, $next);
                    }
                }
                
                // If middleware doesn't exist or handle method doesn't exist, continue
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

        $reflection = is_array($callback)
            ? new \ReflectionMethod($callback[0], $callback[1])
            : new \ReflectionFunction($callback);

        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $className = $type->getName();
                
                // Try to resolve from container first
                $resolved = $this->resolve($className);
                if ($resolved) {
                    $args[] = $resolved;
                } else {
                    // Try to instantiate with dependency resolution
                    $args[] = $this->instantiateClass($className);
                }
            }
        }

        // Merge DI arguments with route parameters
        $args = array_merge($args, array_values($parameters));

        if (is_array($callback)) {
            $controller = $this->instantiateClass($callback[0]);
            $method = $callback[1];
            return $controller->$method(...$args);
        } else {
            return $callback(...$args);
        }
    }

    protected function renderResponse($result): void
    {
        if ($result instanceof Response) {
            $result->send();
        } elseif (is_array($result)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } else {
            // The result should already be sanitized by the Request class or the handler
            echo (string) $result;
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
                // Execute middleware stack which will also execute the route
                $result = $this->executeMiddlewareStack($matchedRoute, $this->request);
                
                // If the result is null or false, it means middleware handled the response
                if ($result !== null && $result !== false) {
                    $this->renderResponse($result);
                }
                return;
            }

            http_response_code(404);
            $errorResponse = $this->errorHandler ? call_user_func($this->errorHandler, 404) : 'Route not found';
            $this->renderResponse($errorResponse);
        } catch (RouteNotFoundException $e) {
            http_response_code(404);
            $errorResponse = $this->errorHandler ? call_user_func($this->errorHandler, 404) : $e->getMessage();
            $this->renderResponse($errorResponse);
        } catch (\Throwable $e) {
            http_response_code(500);
            $errorMessage = 'Server Error - ' . $e->getMessage();
            $errorResponse = $this->errorHandler ? call_user_func($this->errorHandler, 500) : $errorMessage;
            $this->renderResponse($errorResponse);
        }
    }

    private function instantiateClass(string $className)
    {
        if (!class_exists($className)) {
            throw new \Exception("Class $className does not exist");
        }

        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            // No constructor, create instance directly
            return $reflection->newInstance();
        }

        // Get constructor parameters
        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            
            if ($type && !$type->isBuiltin()) {
                $paramClass = $type->getName();
                
                // Try to resolve from container
                $resolved = $this->resolve($paramClass);
                if ($resolved) {
                    $params[] = $resolved;
                } else {
                    // Recursively instantiate dependencies
                    $params[] = $this->instantiateClass($paramClass);
                }
            } else {
                // For primitive types, we need a default value or throw an exception
                if ($param->isDefaultValueAvailable()) {
                    $params[] = $param->getDefaultValue();
                } else {
                    throw new \Exception("Cannot instantiate class $className: Parameter {$param->getName()} has no default value and is not a class");
                }
            }
        }

        return $reflection->newInstanceArgs($params);
    }
}