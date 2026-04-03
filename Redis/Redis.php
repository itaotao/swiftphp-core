<?php

namespace SwiftPHP\Core\Redis;

use Exception;

class Redis
{
    protected static $connection = null;
    protected static $config = [];
    protected static $persistent = false;

    public static function init(array $config = []): void
    {
        self::$config = $config;
        self::$persistent = $config['persistent'] ?? false;
    }

    public static function connection(): \Redis
    {
        if (self::$connection === null) {
            self::connect();
        }
        return self::$connection;
    }

    protected static function connect(): void
    {
        $host = self::$config['host'] ?? '127.0.0.1';
        $port = self::$config['port'] ?? 6379;
        $password = self::$config['password'] ?? null;
        $database = self::$config['database'] ?? 0;
        $timeout = self::$config['timeout'] ?? 0;
        $reserved = self::$config['reserved'] ?? null;
        $retryInterval = self::$config['retry_interval'] ?? 0;

        if (self::$persistent) {
            self::$connection = new \Redis();
            if ($timeout > 0) {
                self::$connection->pconnect($host, $port, $timeout, $reserved, $retryInterval);
            } else {
                self::$connection->pconnect($host, $port);
            }
        } else {
            self::$connection = new \Redis();
            if ($timeout > 0) {
                self::$connection->connect($host, $port, $timeout);
            } else {
                self::$connection->connect($host, $port);
            }
        }

        if ($password !== null) {
            self::$connection->auth($password);
        }

        if ($database > 0) {
            self::$connection->select($database);
        }
    }

    public static function get(string $key)
    {
        $value = self::connection()->get(self::$config['prefix'] ?? '' . $key);
        if ($value === false) {
            return null;
        }
        $decoded = @unserialize($value);
        return $decoded === false ? $value : $decoded;
    }

    public static function set(string $key, $value, int $ttl = 0): bool
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($value)) {
            $value = serialize($value);
        }
        if ($ttl > 0) {
            return self::connection()->setex($key, $ttl, $value);
        }
        return self::connection()->set($key, $value);
    }

    public static function has(string $key): bool
    {
        return self::connection()->exists((self::$config['prefix'] ?? '') . $key) > 0;
    }

    public static function delete(string $key): int
    {
        return self::connection()->del((self::$config['prefix'] ?? '') . $key);
    }

    public static function deleteMultiple(array $keys): int
    {
        $prefix = self::$config['prefix'] ?? '';
        $keys = array_map(function ($key) use ($prefix) {
            return $prefix . $key;
        }, $keys);
        return self::connection()->del($keys);
    }

    public static function expire(string $key, int $ttl): bool
    {
        return self::connection()->expire((self::$config['prefix'] ?? '') . $key, $ttl);
    }

    public static function ttl(string $key): int
    {
        return self::connection()->ttl((self::$config['prefix'] ?? '') . $key);
    }

    public static function increment(string $key, int $value = 1): int
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if ($value === 1) {
            return self::connection()->incr($key);
        }
        return self::connection()->incrby($key, $value);
    }

    public static function decrement(string $key, int $value = 1): int
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if ($value === 1) {
            return self::connection()->decr($key);
        }
        return self::connection()->decrby($key, $value);
    }

    public static function getMultiple(array $keys): array
    {
        $prefix = self::$config['prefix'] ?? '';
        $keys = array_map(function ($key) use ($prefix) {
            return $prefix . $key;
        }, $keys);
        $values = self::connection()->mGet($keys);
        $result = [];
        foreach ($keys as $i => $key) {
            $originalKey = str_replace($prefix, '', $key);
            $value = $values[$i];
            if ($value !== false) {
                $decoded = @unserialize($value);
                $result[$originalKey] = $decoded === false ? $value : $decoded;
            } else {
                $result[$originalKey] = null;
            }
        }
        return $result;
    }

    public static function setMultiple(array $items, int $ttl = 0): bool
    {
        $prefix = self::$config['prefix'] ?? '';
        $pipe = self::connection()->multi(\Redis::PIPELINE);
        foreach ($items as $key => $value) {
            $key = $prefix . $key;
            if (!is_string($value)) {
                $value = serialize($value);
            }
            if ($ttl > 0) {
                $pipe->setex($key, $ttl, $value);
            } else {
                $pipe->set($key, $value);
            }
        }
        return $pipe->exec() !== false;
    }

    public static function hGet(string $key, string $field)
    {
        $value = self::connection()->hGet((self::$config['prefix'] ?? '') . $key, $field);
        if ($value === false) {
            return null;
        }
        $decoded = @unserialize($value);
        return $decoded === false ? $value : $decoded;
    }

    public static function hSet(string $key, string $field, $value): int
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($value)) {
            $value = serialize($value);
        }
        return self::connection()->hSet($key, $field, $value);
    }

    public static function hGetAll(string $key): array
    {
        $data = self::connection()->hGetAll((self::$config['prefix'] ?? '') . $key);
        $result = [];
        foreach ($data as $field => $value) {
            $decoded = @unserialize($value);
            $result[$field] = $decoded === false ? $value : $decoded;
        }
        return $result;
    }

    public static function hExists(string $key, string $field): bool
    {
        return self::connection()->hExists((self::$config['prefix'] ?? '') . $key, $field);
    }

    public static function hDelete(string $key, string ...$fields): int
    {
        return self::connection()->hDel((self::$config['prefix'] ?? '') . $key, ...$fields);
    }

    public static function hIncrBy(string $key, string $field, int $value): int
    {
        return self::connection()->hIncrBy((self::$config['prefix'] ?? '') . $key, $field, $value);
    }

    public static function hLen(string $key): int
    {
        return self::connection()->hLen((self::$config['prefix'] ?? '') . $key);
    }

    public static function lPush(string $key, $value): int
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($value)) {
            $value = serialize($value);
        }
        return self::connection()->lPush($key, $value);
    }

    public static function rPush(string $key, $value): int
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($value)) {
            $value = serialize($value);
        }
        return self::connection()->rPush($key, $value);
    }

    public static function lPop(string $key)
    {
        $value = self::connection()->lPop((self::$config['prefix'] ?? '') . $key);
        if ($value === false) {
            return null;
        }
        $decoded = @unserialize($value);
        return $decoded === false ? $value : $decoded;
    }

    public static function rPop(string $key)
    {
        $value = self::connection()->rPop((self::$config['prefix'] ?? '') . $key);
        if ($value === false) {
            return null;
        }
        $decoded = @unserialize($value);
        return $decoded === false ? $value : $decoded;
    }

    public static function lRange(string $key, int $start, int $end): array
    {
        $data = self::connection()->lRange((self::$config['prefix'] ?? '') . $key, $start, $end);
        $result = [];
        foreach ($data as $value) {
            $decoded = @unserialize($value);
            $result[] = $decoded === false ? $value : $decoded;
        }
        return $result;
    }

    public static function lLen(string $key): int
    {
        return self::connection()->lLen((self::$config['prefix'] ?? '') . $key);
    }

    public static function sAdd(string $key, $value): int
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($value)) {
            $value = serialize($value);
        }
        return self::connection()->sAdd($key, $value);
    }

    public static function sMembers(string $key): array
    {
        $data = self::connection()->sMembers((self::$config['prefix'] ?? '') . $key);
        $result = [];
        foreach ($data as $value) {
            $decoded = @unserialize($value);
            $result[] = $decoded === false ? $value : $decoded;
        }
        return $result;
    }

    public static function sIsMember(string $key, $value): bool
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($value)) {
            $value = serialize($value);
        }
        return self::connection()->sIsMember($key, $value);
    }

    public static function sCard(string $key): int
    {
        return self::connection()->sCard((self::$config['prefix'] ?? '') . $key);
    }

    public static function sRem(string $key, $value): int
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($value)) {
            $value = serialize($value);
        }
        return self::connection()->sRem($key, $value);
    }

    public static function zAdd(string $key, float $score, $member): int
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($member)) {
            $member = serialize($member);
        }
        return self::connection()->zAdd($key, $score, $member);
    }

    public static function zRange(string $key, int $start, int $end, bool $withScores = false): array
    {
        if ($withScores) {
            $data = self::connection()->zRange($key, $start, $end, \Redis::SCORE);
        } else {
            $data = self::connection()->zRange($key, $start, $end);
        }
        $result = [];
        foreach ($data as $member => $score) {
            $decoded = @unserialize($member);
            $member = $decoded === false ? $member : $decoded;
            if ($withScores) {
                $result[$member] = $score;
            } else {
                $result[] = $member;
            }
        }
        return $result;
    }

    public static function zRevRange(string $key, int $start, int $end, bool $withScores = false): array
    {
        if ($withScores) {
            $data = self::connection()->zRevRange($key, $start, $end, \Redis::SCORE);
        } else {
            $data = self::connection()->zRevRange($key, $start, $end);
        }
        $result = [];
        foreach ($data as $member => $score) {
            $decoded = @unserialize($member);
            $member = $decoded === false ? $member : $decoded;
            if ($withScores) {
                $result[$member] = $score;
            } else {
                $result[] = $member;
            }
        }
        return $result;
    }

    public static function zScore(string $key, $member): float
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($member)) {
            $member = serialize($member);
        }
        return self::connection()->zScore($key, $member);
    }

    public static function zRank(string $key, $member): int
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($member)) {
            $member = serialize($member);
        }
        return self::connection()->zRank($key, $member);
    }

    public static function zCard(string $key): int
    {
        return self::connection()->zCard((self::$config['prefix'] ?? '') . $key);
    }

    public static function zDelete(string $key, $member): int
    {
        $key = (self::$config['prefix'] ?? '') . $key;
        if (!is_string($member)) {
            $member = serialize($member);
        }
        return self::connection()->zDelete($key, $member);
    }

    public static function keys(string $pattern): array
    {
        $prefix = self::$config['prefix'] ?? '';
        $keys = self::connection()->keys($prefix . $pattern);
        return array_map(function ($key) use ($prefix) {
            return str_replace($prefix, '', $key);
        }, $keys);
    }

    public static function flushDB(): bool
    {
        return self::connection()->flushDB();
    }

    public static function flushAll(): bool
    {
        return self::connection()->flushAll();
    }

    public static function ping(): string
    {
        return self::connection()->ping();
    }

    public static function info(string $section = null): array
    {
        if ($section) {
            return self::connection()->info($section);
        }
        return self::connection()->info();
    }

    public static function dbsize(): int
    {
        return self::connection()->dbsize();
    }

    public static function type(string $key): int
    {
        return self::connection()->type((self::$config['prefix'] ?? '') . $key);
    }

    public static function close(): bool
    {
        if (self::$connection !== null) {
            $result = self::$connection->close();
            self::$connection = null;
            return $result;
        }
        return true;
    }

    public static function __callStatic($method, $args)
    {
        return call_user_func_array([self::connection(), $method], $args);
    }
}
