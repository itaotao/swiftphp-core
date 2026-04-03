<?php

namespace SwiftPHP\Cookie;

class Cookie
{
    protected static $prefix = 'swift_';
    protected static $path = '/';
    protected static $domain = '';
    protected static $secure = false;
    protected static $httponly = true;
    protected static $samesite = 'Lax';
    protected static $queued = [];

    public static function init(array $config = []): void
    {
        self::$prefix = $config['prefix'] ?? 'swift_';
        self::$path = $config['path'] ?? '/';
        self::$domain = $config['domain'] ?? '';
        self::$secure = $config['secure'] ?? false;
        self::$httponly = $config['httponly'] ?? true;
        self::$samesite = $config['samesite'] ?? 'Lax';
    }

    public static function set(string $name, string $value, int $expire = 0, array $options = []): bool
    {
        $name = self::$prefix . $name;

        $expire = $expire > 0 ? time() + $expire : 0;

        $path = $options['path'] ?? self::$path;
        $domain = $options['domain'] ?? self::$domain;
        $secure = $options['secure'] ?? self::$secure;
        $httponly = $options['httponly'] ?? self::$httponly;
        $samesite = $options['samesite'] ?? self::$samesite;

        $cookieStr = "{$name}=" . urlencode($value);

        if ($expire > 0) {
            $cookieStr .= "; Expires=" . gmdate('D, d M Y H:i:s', $expire) . " GMT";
        }

        $cookieStr .= "; Path={$path}";

        if ($domain) {
            $cookieStr .= "; Domain={$domain}";
        }

        if ($secure) {
            $cookieStr .= "; Secure";
        }

        if ($httponly) {
            $cookieStr .= "; HttpOnly";
        }

        if ($samesite) {
            $cookieStr .= "; SameSite={$samesite}";
        }

        self::$queued[$name] = $cookieStr;

        $_COOKIE[$name] = $value;

        return setcookie($name, $value, [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
        ]);
    }

    public static function get(string $name, $default = null)
    {
        $name = self::$prefix . $name;
        return $_COOKIE[$name] ?? $default;
    }

    public static function has(string $name): bool
    {
        $name = self::$prefix . $name;
        return isset($_COOKIE[$name]);
    }

    public static function delete(string $name, array $options = []): bool
    {
        $name = self::$prefix . $name;

        $path = $options['path'] ?? self::$path;
        $domain = $options['domain'] ?? self::$domain;

        unset($_COOKIE[$name]);

        return setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => $path,
            'domain' => $domain,
            'secure' => self::$secure,
            'httponly' => self::$httponly,
        ]);
    }

    public static function clear(): void
    {
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, self::$prefix) === 0) {
                self::delete(str_replace(self::$prefix, '', $name));
            }
        }
    }

    public static function all(): array
    {
        $cookies = [];
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, self::$prefix) === 0) {
                $cookies[str_replace(self::$prefix, '', $name)] = $value;
            }
        }
        return $cookies;
    }

    public static function queue(string $name, string $value, int $expire = 0, array $options = []): void
    {
        $name = self::$prefix . $name;

        $expire = $expire > 0 ? time() + $expire : 0;

        $path = $options['path'] ?? self::$path;
        $domain = $options['domain'] ?? self::$domain;
        $secure = $options['secure'] ?? self::$secure;
        $httponly = $options['httponly'] ?? self::$httponly;
        $samesite = $options['samesite'] ?? self::$samesite;

        $cookieStr = "{$name}=" . urlencode($value);

        if ($expire > 0) {
            $cookieStr .= "; Expires=" . gmdate('D, d M Y H:i:s', $expire) . " GMT";
        }

        $cookieStr .= "; Path={$path}";

        if ($domain) {
            $cookieStr .= "; Domain={$domain}";
        }

        if ($secure) {
            $cookieStr .= "; Secure";
        }

        if ($httponly) {
            $cookieStr .= "; HttpOnly";
        }

        if ($samesite) {
            $cookieStr .= "; SameSite={$samesite}";
        }

        self::$queued[$name] = $cookieStr;
    }

    public static function flush(): void
    {
        foreach (self::$queued as $cookie) {
            header('Set-Cookie: ' . $cookie, false);
        }
        self::$queued = [];
    }

    public static function getPrefix(): string
    {
        return self::$prefix;
    }
}
