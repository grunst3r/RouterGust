<?php
namespace GustRouter;

class Request {

    public string $method;
    public string $path;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
    }

    public function getPath(): string {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
    }

    public function getMethod(): string {
        return $_SERVER['REQUEST_METHOD'];
    }


    public function post(string $key, $default = null) {
        return $_POST[$key] ?? $default;
    }

    public function get(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }

}
