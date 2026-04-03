<?php

namespace SwiftPHP\Url;

class Url
{
    protected static $root = null;
    protected static $basePath = '';
    protected static $schema = 'http';
    protected static $host = 'localhost';
    protected static $port = 80;

    public static function init(array $config = []): void
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            $parts = explode(':', $host);
            self::$host = $parts[0];
            self::$port = isset($parts[1]) ? (int)$parts[1] : 80;
        }

        if (isset($_SERVER['REQUEST_SCHEME'])) {
            self::$schema = $_SERVER['REQUEST_SCHEME'];
        }

        if (isset($_SERVER['SERVER_PORT'])) {
            self::$port = (int)$_SERVER['SERVER_PORT'];
        }

        self::$root = $config['root'] ?? self::buildRoot();
        self::$basePath = $config['base_path'] ?? '/';
    }

    protected static function buildRoot(): string
    {
        $port = '';
        if (self::$port !== 80 && self::$port !== 443) {
            $port = ':' . self::$port;
        }

        return self::$schema . '://' . self::$host . $port . self::$basePath;
    }

    public static function getRoot(): string
    {
        return self::$root;
    }

    public static function getBasePath(): string
    {
        return self::$basePath;
    }

    public static function getCurrent(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return self::$root . $uri;
    }

    public static function getPath(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }

    public static function url(string $path = '', array $params = []): string
    {
        if (strpos($path, '://') !== false) {
            $url = $path;
        } else {
            $path = '/' . ltrim($path, '/');
            $url = self::$root . $path;
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    public static function route(string $name, array $params = []): string
    {
        $routes = self::getRoutes();
        $path = $routes[$name] ?? $name;

        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        $url = self::$root . $path;

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $url = str_replace('{' . $key . '}', (string)$value, $url);
            }

            $query = [];
            foreach ($params as $key => $value) {
                if (strpos($url, '{' . $key . '}') === false) {
                    $query[$key] = $value;
                }
            }

            if (!empty($query)) {
                $url .= '?' . http_build_query($query);
            }
        }

        return $url;
    }

    protected static function getRoutes(): array
    {
        static $routes = [];
        if (empty($routes)) {
            $routeConfig = config('route') ?: [];
            foreach ($routeConfig as $pattern => $handler) {
                $parts = explode(' ', trim($pattern), 2);
                $path = count($parts) === 2 ? $parts[1] : $parts[0];
                $name = str_replace(['/', '-'], '_', trim($path, '/'));
                $routes[$name] = $path;
                $routes[$path] = $path;
            }
        }
        return $routes;
    }

    public static function previous(array $fallback = []): string
    {
        if (isset($_SERVER['HTTP_REFERER'])) {
            return $_SERVER['HTTP_REFERER'];
        }

        if (!empty($fallback)) {
            return self::url($fallback[0], $fallback[1] ?? []);
        }

        return self::$root;
    }

    public static function isAbsolute(string $url): bool
    {
        return strpos($url, '://') !== false;
    }

    public static function match(string $pattern, string $url): bool
    {
        $pattern = preg_replace('/\{[^\}]+\}/', '([^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';
        return preg_match($pattern, $url) === 1;
    }

    public static function parse(string $url): array
    {
        return parse_url($url);
    }
}
