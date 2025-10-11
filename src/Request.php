<?php

namespace GustRouter;

class Request
{
    public string $method;
    public string $path;
    public array $headers;
    public string $ip;
    public string $userAgent;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = $this->sanitizePath(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
        $this->headers = $this->getHeaders();
        $this->ip = $this->getClientIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    private function sanitizePath(string $path): string
    {
        // Remove potential malicious characters and normalize the path
        $path = filter_var($path, FILTER_SANITIZE_URL);
        return $path ?: '/';
    }

    private function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }

    private function getClientIP(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function post(string $key, $default = null)
    {
        $value = $_POST[$key] ?? $default;
        return $this->sanitizeInput($value);
    }

    public function get(string $key, $default = null)
    {
        $value = $_GET[$key] ?? $default;
        return $this->sanitizeInput($value);
    }

    public function input(string $key, $default = null)
    {
        $value = $this->get($key, $this->post($key));
        return $value ?? $default;
    }

    public function all(): array
    {
        $all = array_merge($_GET, $_POST);
        array_walk_recursive($all, function (&$value) {
            $value = $this->sanitizeInput($value);
        });
        return $all;
    }

    public function header(string $key, $default = null)
    {
        $header = strtoupper(str_replace('-', '_', $key));
        return $this->headers[$header] ?? $default;
    }

    private function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        if (is_string($input)) {
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
        
        return $input;
    }

    public function isJson(): bool
    {
        $contentType = $this->header('CONTENT_TYPE', '');
        return strpos($contentType, 'application/json') !== false;
    }

    public function getJson(): array
    {
        if ($this->isJson()) {
            $input = file_get_contents('php://input');
            return json_decode($input, true) ?: [];
        }
        return [];
    }
    
    public function setHostForTesting(string $host): void
    {
        // This is for testing purposes only
        $_SERVER['HTTP_HOST'] = $host;
        $this->headers['HOST'] = $host;
        $this->headers['HTTP_HOST'] = $host;
    }
}