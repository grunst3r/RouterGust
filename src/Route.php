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
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Route callback must be callable');
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