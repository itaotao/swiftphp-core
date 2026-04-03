<?php

namespace SwiftPHP\Core\Middleware;

use SwiftPHP\Core\Request\Request;
use SwiftPHP\Core\Response\Response;

class Middleware
{
    protected $middlewares = [];
    protected $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function add(string $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function then(callable $callback): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            function ($next, $middleware) {
                return function ($request) use ($next, $middleware) {
                    return $this->resolve($middleware)->handle($request, $next);
                };
            },
            function ($request) use ($callback) {
                return $callback($request);
            }
        );

        return $pipeline($this->request);
    }

    protected function resolve(string $middleware): self
    {
        if (strpos($middleware, '\\') === false) {
            $middlewareClass = 'App\\Middleware\\' . $middleware;
        } else {
            $middlewareClass = $middleware;
        }

        if (!class_exists($middlewareClass)) {
            throw new \Exception("Middleware not found: {$middlewareClass}");
        }

        return new $middlewareClass();
    }

    public function handle(Request $request, callable $next): Response
    {
        return $next($request);
    }

    public static function loadGlobalMiddleware(): array
    {
        $middlewareFile = dirname(__DIR__, 2) . '/config/middleware.php';
        if (file_exists($middlewareFile)) {
            return include $middlewareFile;
        }
        return [];
    }
}
