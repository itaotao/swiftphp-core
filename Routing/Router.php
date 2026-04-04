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
    protected $groupStack = [];
    protected $routeNames = [];
    protected $cached = false;
    protected $cachePath;

    public function __construct()
    {
        $this->cachePath = \SwiftPHP\Path\Path::getRootPath() . '/runtime/route_cache.php';
        $this->loadMiddleware();
        $this->registerAutoloader();

        if ($this->isCacheEnabled() && $this->loadCache()) {
            $this->cached = true;
            return;
        }

        $this->loadRoutes();
    }

    protected function isCacheEnabled(): bool
    {
        return isset($this->middlewareGroups['cache']) &&
               $this->middlewareGroups['cache'] === true;
    }

    protected function loadCache(): bool
    {
        if (!file_exists($this->cachePath)) {
            return false;
        }

        $cache = include $this->cachePath;
        if (!is_array($cache)) {
            return false;
        }

        $this->routes = $cache['routes'] ?? [];
        $this->routeMap = $cache['routeMap'] ?? [];
        $this->routeNames = $cache['routeNames'] ?? [];

        return true;
    }

    protected function saveCache(): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }

        $dir = dirname($this->cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $cache = [
            'routes' => $this->routes,
            'routeMap' => $this->routeMap,
            'routeNames' => $this->routeNames,
        ];

        file_put_contents($this->cachePath, '<?php return ' . var_export($cache, true) . ';');
    }

    public function clearCache(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    protected function registerAutoloader(): void
    {
        spl_autoload_register(function ($class) {
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
        $routeConfigFile = \SwiftPHP\Path\Path::getRootPath() . '/route/route.php';
        if (file_exists($routeConfigFile)) {
            $routes = include $routeConfigFile;

            if ($routes instanceof Router) {
                $routes = $routes->getRoutes();
            }

            foreach ($routes as $pattern => $config) {
                $method = 'GET';
                $path = $pattern;
                $options = [];

                if (is_string($pattern) && preg_match('/^(GET|POST|PUT|DELETE|PATCH|OPTIONS|ANY)\s+\//i', $pattern)) {
                    [$method, $path] = preg_split('/\s+/', $pattern, 2);
                    $method = strtoupper($method);
                }

                if (is_array($config)) {
                    $handler = $config['uses'] ?? '';
                    $middleware = $config['middleware'] ?? [];
                    $options = $config;
                    if ($method === 'ANY') {
                        $this->any($path, $handler, $options);
                    } else {
                        $this->addRoute($method, $path, $handler, $options);
                    }
                } else {
                    if ($method === 'ANY') {
                        $this->any($path, $config);
                    } else {
                        $this->addRoute($method, $path, $config);
                    }
                }
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

    public function get(string $path, string $handler, array $options = []): self
    {
        return $this->addRoute('GET', $path, $handler, $options);
    }

    public function post(string $path, string $handler, array $options = []): self
    {
        return $this->addRoute('POST', $path, $handler, $options);
    }

    public function put(string $path, string $handler, array $options = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $options);
    }

    public function delete(string $path, string $handler, array $options = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $options);
    }

    public function patch(string $path, string $handler, array $options = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $options);
    }

    public function options(string $path, string $handler, array $options = []): self
    {
        return $this->addRoute('OPTIONS', $path, $handler, $options);
    }

    public function any(string $path, string $handler, array $options = []): self
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $method) {
            $this->addRoute($method, $path, $handler, $options);
        }
        return $this;
    }

    public function match(array $methods, string $path, string $handler, array $options = []): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler, $options);
        }
        return $this;
    }

    public function addRoute(string $method, string $path, string $handler, array $options = []): self
    {
        $group = $this->getCurrentGroup();
        $prefix = $group['prefix'] ?? '';
        $groupMiddleware = $group['middleware'] ?? [];
        $groupConstraints = $group['constraints'] ?? [];

        $path = $prefix . '/' . ltrim($path, '/');
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $middleware = array_merge($groupMiddleware, $options['middleware'] ?? []);
        $constraints = array_merge($groupConstraints, $options['where'] ?? []);
        $name = $options['as'] ?? $options['name'] ?? null;

        $pattern = $this->compilePattern($path, $constraints);

        $route = [
            'handler' => $handler,
            'middleware' => $middleware,
            'group' => $group['name'] ?? null,
            'constraints' => $constraints,
            'originalPath' => $path,
        ];

        if ($name) {
            $this->routeNames[$name] = $method . ':' . $pattern;
        }

        $this->routes[$method][$pattern] = $route;
        $this->routeMap[$method . ':' . $pattern] = $route;

        return $this;
    }

    protected function compilePattern(string $path, array $constraints): string
    {
        $pattern = $path;

        if (preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $path, $matches)) {
            foreach ($matches[1] as $param) {
                if (isset($constraints[$param])) {
                    $pattern = str_replace('{' . $param . '}', '(' . $constraints[$param] . ')', $pattern);
                } else {
                    $pattern = str_replace('{' . $param . '}', '([^/]+)', $pattern);
                }
            }
        }

        return $pattern;
    }

    protected function getCurrentGroup(): ?array
    {
        return end($this->groupStack) ?: null;
    }

    public function group(array $attributes, callable $callback): self
    {
        $this->groupStack[] = [
            'prefix' => $attributes['prefix'] ?? '',
            'middleware' => $attributes['middleware'] ?? [],
            'constraints' => $attributes['where'] ?? [],
            'name' => $attributes['as'] ?? null,
        ];

        $callback($this);

        array_pop($this->groupStack);

        return $this;
    }

    public function resource(string $name, string $controller, array $options = []): self
    {
        $middleware = $options['middleware'] ?? [];
        $only = $options['only'] ?? null;
        $except = $options['except'] ?? null;
        $prefix = $options['prefix'] ?? '';
        $names = $options['names'] ?? [];

        $resourceMethods = [
            'index' => ['GET', '/' . $name],
            'create' => ['GET', '/' . $name . '/create'],
            'store' => ['POST', '/' . $name],
            'show' => ['GET', '/' . $name . '/{id}'],
            'edit' => ['GET', '/' . $name . '/{id}/edit'],
            'update' => ['PUT', '/' . $name . '/{id}'],
            'destroy' => ['DELETE', '/' . $name . '/{id}'],
        ];

        if ($only) {
            $resourceMethods = array_intersect_key($resourceMethods, array_flip($only));
        } elseif ($except) {
            $resourceMethods = array_diff_key($resourceMethods, array_flip($except));
        }

        foreach ($resourceMethods as $method => $config) {
            list($httpMethod, $path) = $config;
            $routeName = $names[$method] ?? $name . '.' . $method;
            $fullPath = $prefix . $path;

            $this->addRoute($httpMethod, $fullPath, $controller . '@' . $method, [
                'middleware' => $middleware,
                'as' => $routeName,
            ]);
        }

        return $this;
    }

    public function name(string $name): self
    {
        if (!empty($this->groupStack)) {
            $current = &$this->groupStack[count($this->groupStack) - 1];
            $current['name'] = $name;
        }
        return $this;
    }

    public function getRoutes(): array
    {
        $routes = [];
        foreach ($this->routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $path => $route) {
                $pattern = strtolower($method) . ' ' . $route['originalPath'];
                $routes[$pattern] = $route['handler'];
            }
        }
        return $routes;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routeMap as $key => $route) {
            if (strpos($key, $method . ':') !== 0) {
                continue;
            }

            $pattern = substr($key, strlen($method) + 1);

            if ($this->matchRoute($path, $pattern, $route, $request)) {
                return $this->handle($route, $request);
            }
        }

        $response = $this->handleAutoRoute($method, $path, $request);
        if ($response !== null) {
            return $response;
        }

        return $this->notFound($request);
    }

    protected function matchRoute(string $path, string $pattern, array $route, Request $request): bool
    {
        $escapedPattern = str_replace('/', '\/', $pattern);
        if (!preg_match('/^' . $escapedPattern . '$/', $path, $matches)) {
            return false;
        }

        if (preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $route['originalPath'], $paramNames)) {
            array_shift($matches);
            $params = array_combine($paramNames[1], $matches);
            foreach ($params as $key => $value) {
                $request->setParam($key, $value);
            }
        }

        return true;
    }

    protected function handle(array $route, Request $request): Response
    {
        $handler = $route['handler'];
        $middleware = $route['middleware'] ?? [];
        $group = $route['group'] ?? null;

        $middleware = array_merge(
            $this->middlewareGroups['global'] ?? [],
            $this->getGroupMiddleware($group),
            $this->matchMiddlewareByPath($request->path()),
            $middleware
        );

        $middleware = $this->filterMiddlewareByOnlyExcept($middleware, $request->path());

        list($class, $action) = explode('@', $handler);

        if (strpos($class, "\\") === false) {
            $class = "App\\Controller\\" . $class;
        }

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

    protected function filterMiddlewareByOnlyExcept(array $middleware, string $path): array
    {
        $only = $this->middlewareGroups['only'] ?? [];
        $except = $this->middlewareGroups['except'] ?? [];

        if (!empty($only) && !$this->pathMatchesPatterns($path, $only)) {
            return [];
        }

        if (!empty($except) && $this->pathMatchesPatterns($path, $except)) {
            return [];
        }

        return $middleware;
    }

    protected function pathMatchesPatterns(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = str_replace(['/', '*'], ['\/', '.*'], $pattern);
            if (preg_match('/^' . $pattern . '$/', $path)) {
                return true;
            }
        }
        return false;
    }

    protected function getGroupMiddleware(?string $group): array
    {
        if ($group !== null) {
            $groups = $this->middlewareGroups['groups'] ?? [];
            return $groups[$group] ?? [];
        }
        return [];
    }

    protected function matchMiddlewareByPath(string $path): array
    {
        $prefixMap = $this->middlewareGroups['prefix'] ?? [];
        $groups = $this->middlewareGroups['groups'] ?? [];
        $matched = [];

        foreach ($prefixMap as $prefix => $groupName) {
            if (strpos($path, $prefix) === 0) {
                if (isset($groups[$groupName])) {
                    $matched = array_merge($matched, $groups[$groupName]);
                }
            }
        }

        return $matched;
    }

    public function url(string $name, array $params = []): string
    {
        if (!isset($this->routeNames[$name])) {
            return '';
        }

        $routeKey = $this->routeNames[$name];
        $parts = explode(':', $routeKey, 2);
        $path = $parts[1] ?? '';

        foreach ($params as $key => $value) {
            $path = preg_replace('/\{' . $key . '\}/', (string)$value, $path);
        }

        $path = preg_replace('/\{[^}]+\}/', '', $path);

        return $path;
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
                    if (preg_match('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $part, $matches)) {
                        $request->setParam($matches[1], '');
                        $action = preg_replace('/\{[^}]+\}/', '', $part);
                    } else {
                        $action = $part;
                    }
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
            $this->matchMiddlewareByPath('/' . $path)
        );

        $middleware = $this->filterMiddlewareByOnlyExcept($middleware, '/' . $path);

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