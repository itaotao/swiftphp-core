<?php

namespace SwiftPHP\Routing;

use SwiftPHP\Exception\Handler;
use SwiftPHP\Exception\HttpException;
use SwiftPHP\Middleware\Middleware;
use SwiftPHP\Request\Request;
use SwiftPHP\Response\Response;

class Router
{
    protected $routes = [];
    protected $routeMap = [];
    protected $middlewareGroups = [];

    public function __construct()
    {
        $this->loadRoutes();
        $this->loadMiddleware();
        $this->registerAutoloader();
    }

    protected function registerAutoloader(): void
    {
        spl_autoload_register(function ($class) {
            // 处理 App\Controller 命名空间
            if (strpos($class, 'App\\Controller\\') === 0) {
                $classPath = str_replace('App\\Controller\\', '', $class);
                $filePath = \SwiftPHP\Path\Path::getRootPath() . '/app/controller/' . str_replace('\\', '/', $classPath) . '.php';
                if (file_exists($filePath)) {
                    require_once $filePath;
                    return true;
                }
            }
            return false;
        });
    }

    protected function loadRoutes(): void
    {
        $routeConfigFile = \SwiftPHP\Path\Path::getRootPath() . '/config/route.php';
        if (file_exists($routeConfigFile)) {
            $routes = include $routeConfigFile;
            foreach ($routes as $pattern => $handler) {
                $this->addRoute($pattern, $handler);
            }
        }
    }

    protected function loadMiddleware(): void
    {
        $middlewareFile = \SwiftPHP\Path\Path::getRootPath() . '/config/middleware.php';
        if (file_exists($middlewareFile)) {
            $this->middlewareGroups = include $middlewareFile;
        }
    }

    public function addRoute(string $pattern, string $handler, array $middleware = []): void
    {
        $parts = explode(' ', $pattern, 2);
        $method = 'GET';
        $path = '/';

        if (count($parts) === 2) {
            $method = strtoupper($parts[0]);
            $path = $parts[1];
        } else {
            $path = $parts[0];
        }

        $this->routes[$method][$path] = [
            'handler' => $handler,
            'middleware' => $middleware
        ];
        $this->routeMap[$method . ':' . $path] = [
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        if (isset($this->routeMap[$method . ':' . $path])) {
            return $this->handle($this->routeMap[$method . ':' . $path], $request);
        }

        $response = $this->handleAutoRoute($method, $path, $request);
        if ($response !== null) {
            return $response;
        }

        return $this->notFound($request);
    }

    protected function handle(array $route, Request $request): Response
    {
        $handler = $route['handler'];
        $middleware = $route['middleware'] ?? [];

        $middleware = array_merge(
            $this->middlewareGroups['global'] ?? [],
            $middleware
        );

        list($class, $action) = explode('@', $handler);

        if (!class_exists($class)) {
            return $this->notFound($request);
        }

        $controller = new $class();

        if (!method_exists($controller, $action)) {
            return $this->notFound($request);
        }

        $callback = function ($request) use ($controller, $action) {
            return $controller->$action($request);
        };

        $middlewareInstance = new Middleware($request);
        foreach ($middleware as $m) {
            $middlewareInstance->add($m);
        }

        return $middlewareInstance->then($callback);
    }

    protected function handleAutoRoute(string $method, string $path, Request $request): ?Response
    {
        $path = trim($path, '/');

        if (empty($path)) {
            $class = 'App\Controller\IndexController';
            $action = 'index';
        } else {
            $parts = explode('/', $path);
            $className = '';
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $action = $part;
                } else {
                    $className .= '\\' . ucfirst($part);
                }
            }
            $class = 'App\Controller' . $className . 'Controller';
            $action = $action ?? 'index';
        }

        if (!class_exists($class)) {
            return null;
        }

        if (!method_exists($class, $action)) {
            return null;
        }

        $middleware = array_merge(
            $this->middlewareGroups['global'] ?? [],
            $this->middlewareGroups['group']['api'] ?? []
        );

        $controller = new $class();

        $callback = function ($request) use ($controller, $action) {
            return $controller->$action($request);
        };

        $middlewareInstance = new Middleware($request);
        foreach ($middleware as $m) {
            $middlewareInstance->add($m);
        }

        return $middlewareInstance->then($callback);
    }

    protected function notFound(?Request $request = null): Response
    {
        $e = new HttpException(404, 'Not Found');
        $exception = Handler::render($e, $request);

        return new Response($exception['status_code'], [], $exception['content']);
    }
}