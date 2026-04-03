<?php

namespace SwiftPHP\Session;

class Session
{
    protected static $initialized = false;
    protected static $sessionId = '';
    protected static $prefix = 'swift_';
    protected static $data = [];
    protected static $flash = [];
    protected static $driver = 'file';
    protected static $path;
    protected static $lifetime = 120;

    public static function getInstance(): self
    {
        return new self();
    }

    public static function init(array $config = []): void
    {
        if (self::$initialized) {
            return;
        }

        self::$prefix = $config['prefix'] ?? 'swift_';
        self::$driver = $config['driver'] ?? 'file';
        self::$lifetime = $config['lifetime'] ?? 120;

        if (self::$driver === 'file') {
            self::$path = $config['path'] ?? \SwiftPHP\Path\Path::getRootPath() . '/runtime/session';
            if (!is_dir(self::$path)) {
                mkdir(self::$path, 0755, true);
            }
        }

        self::start();
        self::$initialized = true;
    }

    protected static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::$sessionId = session_id();

        if (isset($_SESSION[self::$prefix])) {
            self::$data = $_SESSION[self::$prefix];
        }

        self::gc();
    }

    public static function get(string $name, $default = null)
    {
        if (strpos($name, '.') !== false) {
            $keys = explode('.', $name);
            $value = self::$data;
            foreach ($keys as $key) {
                if (is_array($value) && isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return $default;
                }
            }
            return $value;
        }

        return self::$data[$name] ?? $default;
    }

    public static function set(string $name, $value): void
    {
        if (strpos($name, '.') !== false) {
            $keys = explode('.', $name);
            $data = &self::$data;
            foreach ($keys as $i => $key) {
                if ($i === count($keys) - 1) {
                    $data[$key] = $value;
                } else {
                    if (!isset($data[$key]) || !is_array($data[$key])) {
                        $data[$key] = [];
                    }
                    $data = &$data[$key];
                }
            }
        } else {
            self::$data[$name] = $value;
        }

        self::save();
    }

    public static function has(string $name): bool
    {
        if (strpos($name, '.') !== false) {
            $keys = explode('.', $name);
            $value = self::$data;
            foreach ($keys as $key) {
                if (is_array($value) && isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return false;
                }
            }
            return true;
        }

        return isset(self::$data[$name]);
    }

    public static function delete(string $name): void
    {
        if (strpos($name, '.') !== false) {
            $keys = explode('.', $name);
            $data = &self::$data;
            foreach ($keys as $i => $key) {
                if ($i === count($keys) - 1) {
                    unset($data[$key]);
                } else {
                    if (!isset($data[$key])) {
                        return;
                    }
                    $data = &$data[$key];
                }
            }
        } else {
            unset(self::$data[$name]);
        }

        self::save();
    }

    public static function clear(): void
    {
        self::$data = [];
        self::save();
    }

    public static function flash(string $name, $value = null): void
    {
        if ($value === null) {
            self::$flash[$name] = true;
        } else {
            self::$flash[$name] = $value;
        }
        self::save();
    }

    public static function getFlash(string $name, $default = null)
    {
        return self::$flash[$name] ?? $default;
    }

    public static function clearFlash(): void
    {
        self::$flash = [];
        self::save();
    }

    public static function all(): array
    {
        return self::$data;
    }

    public static function getId(): string
    {
        return self::$sessionId;
    }

    public static function regenerate(): string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$sessionId = session_id();
        return self::$sessionId;
    }

    public static function destroy(): void
    {
        self::$data = [];
        self::$flash = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    protected static function save(): void
    {
        $_SESSION[self::$prefix] = self::$data;
        $_SESSION[self::$prefix . '_flash'] = self::$flash;
    }

    protected static function gc(): void
    {
        if (self::$driver !== 'file') {
            return;
        }

        $files = glob(self::$path . '/sess_*');
        if (!$files) {
            return;
        }

        $now = time();
        foreach ($files as $file) {
            if (is_file($file)) {
                $mtime = filemtime($file);
                if ($mtime && ($now - $mtime) > self::$lifetime) {
                    @unlink($file);
                }
            }
        }
    }

    public static function isInitialized(): bool
    {
        return self::$initialized;
    }
}
