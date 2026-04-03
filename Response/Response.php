<?php

namespace SwiftPHP\Core\Response;

class Response
{
    protected $statusCode = 200;
    protected $headers = [];
    protected $body = '';

    public function __construct($statusCode = 200, $headers = [], $body = '')
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function __toString(): string
    {
        return $this->send();
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

    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    public static function create($body = '', int $statusCode = 200, array $headers = []): self
    {
        return new self($statusCode, $headers, $body);
    }

    public static function json($data = [], int $statusCode = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        return new self($statusCode, ['Content-Type' => 'application/json'], $body);
    }

    public static function xml($data = '', int $statusCode = 200): self
    {
        return new self($statusCode, ['Content-Type' => 'text/xml'], $data);
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self($statusCode, ['Location' => $url], '');
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return new self(404, [], $message);
    }

    public static function serverError(string $message = 'Internal Server Error'): self
    {
        return new self(500, [], $message);
    }

    public function send(): string
    {
        $statusText = $this->getStatusText($this->statusCode);
        $headerStr = "HTTP/1.1 {$this->statusCode} {$statusText}\r\n";

        foreach ($this->headers as $name => $value) {
            $headerStr .= "{$name}: {$value}\r\n";
        }

        $headerStr .= "Content-Length: " . strlen($this->body) . "\r\n";
        $headerStr .= "\r\n";

        return $headerStr . $this->body;
    }

    protected function getStatusText(int $code): string
    {
        $statusTexts = [
            200 => 'OK',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];

        return $statusTexts[$code] ?? 'Unknown';
    }
}
