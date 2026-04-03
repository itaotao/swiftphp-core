<?php

namespace SwiftPHP\Request;

use Workerman\Protocols\Http\Request as WorkermanRequest;

class Request
{
    protected $workermanRequest;
    protected $get = [];
    protected $post = [];
    protected $header = [];
    protected $method = 'GET';
    protected $path = '/';
    protected $body = '';
    protected $params = [];

    public function __construct($data = null)
    {
        if ($data instanceof WorkermanRequest) {
            $this->workermanRequest = $data;
            $this->parseWorkermanRequest();
        } elseif (is_array($data)) {
            $this->parseRawData($data);
        }
    }

    protected function parseWorkermanRequest(): void
    {
        if ($this->workermanRequest) {
            $this->get = $this->workermanRequest->get() ?? [];
            $this->post = $this->workermanRequest->post() ?? [];
            $this->header = $this->workermanRequest->header() ?? [];
            $this->method = $this->workermanRequest->method();
            $this->path = $this->workermanRequest->path();
            $this->body = $this->workermanRequest->rawBody();
        }
    }

    protected function parseRawData(array $data): void
    {
        $this->method = $data['method'] ?? 'GET';
        $this->path = $data['path'] ?? '/';
        $this->header = $data['header'] ?? [];
        $this->get = $data['get'] ?? [];
        $this->post = $data['post'] ?? [];
        $this->body = $data['body'] ?? '';
    }

    public function method(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function get($name = null, $default = null)
    {
        if ($name === null) {
            return $this->get;
        }
        return $this->get[$name] ?? $default;
    }

    public function setGet(array $get): self
    {
        $this->get = $get;
        return $this;
    }

    public function post($name = null, $default = null)
    {
        if ($name === null) {
            return $this->post;
        }
        return $this->post[$name] ?? $default;
    }

    public function setPost(array $post): self
    {
        $this->post = $post;
        return $this;
    }

    public function param($name = null, $default = null)
    {
        if (empty($this->params)) {
            $this->params = array_merge($this->get, $this->post);
        }

        if ($name === null) {
            return $this->params;
        }
        return $this->params[$name] ?? $default;
    }

    public function header($name = null, $default = null)
    {
        if ($name === null) {
            return $this->header;
        }
        $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
        return $this->header[$name] ?? $default;
    }

    public function setHeader(array $header): self
    {
        $this->header = $header;
        return $this;
    }

    public function input($name, $default = null)
    {
        return $this->param($name, $default);
    }

    public function has($name): bool
    {
        return isset($this->param()[$name]);
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function body(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }
}