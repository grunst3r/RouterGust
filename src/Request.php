<?php
namespace GustRouter;

class Request {
    public function getPath(): string {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
    }

    public function getMethod(): string {
        return $_SERVER['REQUEST_METHOD'];
    }
}
