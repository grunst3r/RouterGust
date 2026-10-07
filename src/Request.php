<?php

namespace GustRouter;

class Request
{
    public string $method;
    public string $path;
    public array $headers;
    public string $ip;
    public string $userAgent;

    private bool $jsonParsed = false;
    private array $jsonCache = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path = $this->sanitizePath(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
        $this->headers = $this->getHeaders();
        $this->ip = $this->getClientIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    private function sanitizePath(string $path): string
    {
        // Strip control characters / null bytes without destructively filtering
        // the URL (percent-encoding must be preserved for correct matching).
        $path = str_replace(["\0", "\r", "\n"], '', $path);

        if ($path === '') {
            return '/';
        }

        return $path[0] === '/' ? $path : '/' . $path;
    }

    private function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[self::normalizeHeaderKey(substr($key, 5))] = $value;
            }
        }

        // Content-Type and Content-Length are not prefixed with HTTP_.
        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $key) {
            if (isset($_SERVER[$key])) {
                $headers[self::normalizeHeaderKey($key)] = $_SERVER[$key];
            }
        }

        return $headers;
    }

    private static function normalizeHeaderKey(string $key): string
    {
        return strtoupper(str_replace('-', '_', $key));
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
        if (array_key_exists($key, $_GET)) {
            return $this->sanitizeInput($_GET[$key]);
        }

        if (array_key_exists($key, $_POST)) {
            return $this->sanitizeInput($_POST[$key]);
        }

        return $default;
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
        return $this->headers[self::normalizeHeaderKey($key)] ?? $default;
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
        $contentType = (string) $this->header('Content-Type', '');
        return stripos($contentType, 'application/json') !== false;
    }

    /**
     * Return the raw decoded JSON body without sanitization.
     */
    public function json(): array
    {
        if ($this->jsonParsed) {
            return $this->jsonCache;
        }
        $this->jsonParsed = true;

        $body = file_get_contents('php://input');
        if ($body === false || trim($body) === '') {
            return $this->jsonCache = [];
        }

        // Only decode when the payload actually looks like JSON. This avoids
        // treating arbitrary request bodies as JSON.
        $trimmed = ltrim($body);
        $looksLikeJson = ($trimmed[0] ?? '') === '{' || ($trimmed[0] ?? '') === '[';

        if (!$this->isJson() && !$looksLikeJson) {
            return $this->jsonCache = [];
        }

        $decoded = json_decode($body, true);
        return $this->jsonCache = is_array($decoded) ? $decoded : [];
    }

    /**
     * Return the decoded JSON body with all string values sanitized (XSS-safe).
     */
    public function getJson(): array
    {
        return $this->sanitizeInput($this->json());
    }

    public function setHostForTesting(string $host): void
    {
        // This is for testing purposes only
        $_SERVER['HTTP_HOST'] = $host;
        $this->headers[self::normalizeHeaderKey('Host')] = $host;
    }

    public function setBodyForTesting(string $body): void
    {
        // This is for testing purposes only: inject a raw request body so the
        // JSON helpers can be exercised without a real SAPI request.
        $this->jsonParsed = true;
        $decoded = json_decode($body, true);
        $this->jsonCache = is_array($decoded) ? $decoded : [];
    }
}
