<?php

namespace SwiftPHP\Cache;

class Cache
{
    const FILE = 'file';
    const MEMORY = 'memory';
    const REDIS = 'redis';

    protected static $driver = self::FILE;
    protected static $path;
    protected static $memoryStore = [];
    protected static $prefix = 'swift_';
    protected static $expire = 0;

    public static function init(array $config = []): void
    {
        self::$driver = $config['driver'] ?? self::FILE;
        self::$path = $config['path'] ?? \SwiftPHP\Path\Path::getRootPath() . '/runtime/cache';
        self::$prefix = $config['prefix'] ?? 'swift_';
        self::$expire = $config['expire'] ?? 0;

        if (self::$driver === self::FILE && !is_dir(self::$path)) {
            mkdir(self::$path, 0755, true);
        }

        if (self::$driver === self::REDIS && !empty($config['redis'])) {
            \SwiftPHP\Redis\Redis::init($config['redis']);
        }
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    public static function get(string $key, $default = null)
    {
        $key = self::sanitizeKey($key);

        if (self::$driver === self::MEMORY) {
            if (!isset(self::$memoryStore[$key])) {
                return $default;
            }
            $item = self::$memoryStore[$key];
            return self::checkExpire($key, $item, $default);
        }

        if (self::$driver === self::REDIS) {
            return \SwiftPHP\Redis\Redis::get($key) ?? $default;
        }

        $file = self::getCacheFile($key);
        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $item = unserialize($content);
        return self::checkExpire($key, $item, $default);
    }

    protected static function checkExpire(string $key, array $item, $default)
    {
        if ($item['expire'] > 0 && $item['expire'] < time()) {
            self::delete($key);
            return $default;
        }
        return $item['value'];
    }

    public static function set(string $key, $value, int $ttl = 0): bool
    {
        $key = self::sanitizeKey($key);
        $expire = $ttl > 0 ? time() + $ttl : 0;

        if (self::$driver === self::MEMORY) {
            self::$memoryStore[$key] = [
                'value' => $value,
                'expire' => $expire,
            ];
            return true;
        }

        if (self::$driver === self::REDIS) {
            return \SwiftPHP\Redis\Redis::set($key, $value, $ttl);
        }

        $file = self::getCacheFile($key);
        $item = [
            'value' => $value,
            'expire' => $expire,
        ];

        $result = file_put_contents($file, serialize($item), LOCK_EX);
        return $result !== false;
    }

    public static function pull(string $key, $default = null)
    {
        $value = self::get($key, $default);
        self::delete($key);
        return $value;
    }

    public static function delete(string $key): bool
    {
        $key = self::sanitizeKey($key);

        if (self::$driver === self::MEMORY) {
            unset(self::$memoryStore[$key]);
            return true;
        }

        if (self::$driver === self::REDIS) {
            return \SwiftPHP\Redis\Redis::delete($key) > 0;
        }

        $file = self::getCacheFile($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    public static function clear(): bool
    {
        if (self::$driver === self::MEMORY) {
            self::$memoryStore = [];
            return true;
        }

        if (self::$driver === self::REDIS) {
            $keys = \SwiftPHP\Redis\Redis::keys('*');
            if (!empty($keys)) {
                \SwiftPHP\Redis\Redis::deleteMultiple($keys);
            }
            return true;
        }

        $files = glob(self::$path . '/' . self::$prefix . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    public static function remember(string $key, int $ttl, callable $callback)
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function increment(string $key, int $step = 1): int
    {
        $value = (int)self::get($key, 0);
        $value += $step;
        self::set($key, $value);
        return $value;
    }

    public static function decrement(string $key, int $step = 1): int
    {
        return self::increment($key, -$step);
    }

    public static function flush(): bool
    {
        return self::clear();
    }

    protected static function sanitizeKey(string $key): string
    {
        return self::$prefix . $key;
    }

    protected static function getCacheFile(string $key): string
    {
        $hash = md5($key);
        return self::$path . '/' . $hash . '.cache';
    }

    public static function getMemoryStore(): array
    {
        return self::$memoryStore;
    }

    public static function tags(array $tags): TaggedCache
    {
        return new TaggedCache($tags);
    }
}

class TaggedCache
{
    protected $tags = [];
    protected $cache = [];

    public function __construct(array $tags)
    {
        $this->tags = $tags;
    }

    public function get(string $key, $default = null)
    {
        return Cache::get($this->taggedKey($key), $default);
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        return Cache::set($this->taggedKey($key), $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return Cache::delete($this->taggedKey($key));
    }

    public function flush(): bool
    {
        $tagPrefix = implode('_', $this->tags) . '_';
        $keys = [];
        $memoryStore = Cache::getMemoryStore();

        foreach ($memoryStore as $key => $item) {
            if (strpos($key, $tagPrefix) === 0) {
                $keys[] = $key;
            }
        }

        foreach ($keys as $key) {
            Cache::delete($key);
        }

        return true;
    }

    protected function taggedKey(string $key): string
    {
        return implode('_', $this->tags) . '_' . $key;
    }
}
