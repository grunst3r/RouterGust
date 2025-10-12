<?php

namespace GustRouter;

class Route 
{
    public string $path;
    public $callback; // Can't use callable type hint in property declaration
    public array $methods;
    public array $parameters = [];
    public ?string $middleware = null;
    public ?string $routeName = null;
    public ?string $domain = null;
    public array $middlewareStack = [];
    public array $wheres = []; // Store parameter validation rules

    public function __construct(string $path, $callback, array $methods) 
    {
        // Validate callback is callable before creating route
        // For arrays (controller@method format), we'll check if class and method exist separately
        if (!is_callable($callback)) {
            // Check if it's a controller@method format and if both class and method exist
            if (is_array($callback) && count($callback) === 2 && is_string($callback[0]) && is_string($callback[1])) {
                $class = $callback[0];
                $method = $callback[1];
                
                if (class_exists($class)) {
                    // Class exists, now check if method exists
                    if (!method_exists($class, $method)) {
                        throw new \InvalidArgumentException("Controller method does not exist: {$class}::{$method} for path: {$path}");
                    }
                    // If both class and method exist, it's a valid controller callback, so continue
                } else {
                    // Class doesn't exist
                    throw new \InvalidArgumentException("Controller class does not exist: {$class} for path: {$path}");
                }
            } else {
                // Not a controller callback, check general callable requirement
                // Determine the type of the invalid callback for a more descriptive error
                $callbackType = gettype($callback);
                if (is_object($callback)) {
                    $callbackType = get_class($callback);
                }
                
                throw new \InvalidArgumentException("Route callback must be callable, but received {$callbackType}: " . var_export($callback, true) . " for path {$path}");
            }
        }
        
        $this->path = $path;
        $this->callback = $callback;
        $this->methods = $methods;
    }

    public function middleware(string $middleware): self 
    {
        // Add to middleware stack instead of single middleware
        $this->middlewareStack[] = $middleware;
        return $this;
    }

    public function name(string $name): self 
    {
        $this->routeName = $name;
        return $this;
    }
    
    public function where($name, $expression = null): self
    {
        if (is_array($name)) {
            // Multiple parameters with expressions
            foreach ($name as $param => $regex) {
                $this->wheres[$param] = $regex;
            }
        } else {
            // Single parameter with expression
            $this->wheres[$name] = $expression;
        }
        
        return $this;
    }
    
    public function getMiddlewareStack(): array
    {
        return $this->middlewareStack;
    }
    
    public function getWheres(): array
    {
        return $this->wheres;
    }
}