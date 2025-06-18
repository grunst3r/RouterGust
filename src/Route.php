<?php
namespace GustRouter;

class Route {
    public string $path;
    public $callback;
    public array $methods;
    public array $parameters = [];
    public ?string $middleware = null;
    public ?string $routeName = null;

    public function __construct(string $path, $callback, array $methods) {
        $this->path = $path;
        $this->callback = $callback;
        $this->methods = $methods;
    }

    public function middleware(string $middleware): self {
        $this->middleware = $middleware;
        return $this;
    }

    public function name(string $name): self {
        $this->routeName = $name;
        return $this;
    }

    
}
