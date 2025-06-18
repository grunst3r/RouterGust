<?php
namespace GustRouter;

class Response {
    public static function abort(int $code, string $message = ''): void {
        http_response_code($code);
        echo $message ?: 'Error';
        exit;
    }
}
