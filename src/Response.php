<?php

namespace GustRouter;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content;

    public function __construct(string $content = '', int $statusCode = 200)
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
    }

    public function withStatus(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function json(array $data): self
    {
        $this->headers['Content-Type'] = 'application/json';
        $this->content = json_encode($data);
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo $this->content;
    }

    public static function abort(int $code, string $message = ''): void
    {
        http_response_code($code);
        echo htmlspecialchars($message ?: 'Error', ENT_QUOTES, 'UTF-8');
        exit;
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        $response = new self('', $statusCode);
        $response->headers['Location'] = $url;
        return $response;
    }
}