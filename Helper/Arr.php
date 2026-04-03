<?php

namespace SwiftPHP\Core\Helper;

class Arr
{
    public static function get(array $array, $key, $default = null)
    {
        if (strpos($key, '.') === false) {
            return $array[$key] ?? $default;
        }

        $keys = explode('.', $key);
        $result = $array;

        foreach ($keys as $k) {
            if (!is_array($result) || !array_key_exists($k, $result)) {
                return $default;
            }
            $result = $result[$k];
        }

        return $result;
    }

    public static function set(array &$array, $key, $value): void
    {
        if (strpos($key, '.') === false) {
            $array[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }

    public static function has(array $array, $key): bool
    {
        if (strpos($key, '.') === false) {
            return isset($array[$key]);
        }

        $keys = explode('.', $key);
        $result = $array;

        foreach ($keys as $k) {
            if (!is_array($result) || !isset($result[$k])) {
                return false;
            }
            $result = $result[$k];
        }

        return true;
    }

    public static function forget(array &$array, $keys): void
    {
        $original = &$array;
        $keys = (array)$keys;

        foreach ($keys as $key) {
            if (strpos($key, '.') === false) {
                unset($array[$key]);
                continue;
            }

            $parts = explode('.', $key);
            $current = &$original;

            foreach ($parts as $i => $k) {
                if ($i === count($parts) - 1) {
                    unset($current[$k]);
                } else {
                    if (!isset($current[$k]) || !is_array($current[$k])) {
                        break;
                    }
                    $current = &$current[$k];
                }
            }
        }
    }

    public static function pull(array &$array, $key, $default = null)
    {
        $value = self::get($array, $key, $default);
        self::forget($array, $key);
        return $value;
    }

    public static function only(array $array, $keys): array
    {
        return array_intersect_key($array, array_flip((array)$keys));
    }

    public static function except(array $array, $keys): array
    {
        foreach ((array)$keys as $key) {
            self::forget($array, $key);
        }
        return $array;
    }

    public static function first(array $array, callable $callback = null, $default = null)
    {
        if ($callback === null) {
            return $array[0] ?? $default;
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    public static function last(array $array, callable $callback = null, $default = null)
    {
        if ($callback === null) {
            return $array[count($array) - 1] ?? $default;
        }

        return self::first(array_reverse($array, true), $callback, $default);
    }

    public static function flatten(array $array, int $depth = -1): array
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, self::flatten($item, $depth - 1));
            }
        }

        return $result;
    }

    public static function where(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public static function map(array $array, callable $callback): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[$key] = $callback($value, $key);
        }
        return $result;
    }

    public static function pluck(array $array, $value, $key = null): array
    {
        $results = [];
        foreach ($array as $item) {
            $itemValue = is_array($item) ? ($item[$value] ?? $item) : (is_object($item) ? ($item->$value ?? null) : null);
            if ($key !== null) {
                $itemKey = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null);
                if ($itemKey !== null) {
                    $results[$itemKey] = $itemValue;
                }
            } else {
                $results[] = $itemValue;
            }
        }
        return $results;
    }

    public static function groupBy(array $array, $key): array
    {
        $results = [];
        foreach ($array as $item) {
            $groupKey = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null);
            if ($groupKey !== null) {
                $results[$groupKey][] = $item;
            }
        }
        return $results;
    }

    public static function sortBy(array $array, $key, int $options = SORT_REGULAR, bool $descending = false): array
    {
        $results = [];
        foreach ($array as $k => $v) {
            $results[$k] = is_array($v) ? ($v[$key] ?? null) : (is_object($v) ? ($v->$key ?? null) : null);
        }

        array_multisort($results, $descending ? SORT_DESC : SORT_ASC, $options, $array);
        return $array;
    }

    public static function unique(array $array): array
    {
        return array_unique($array);
    }

    public static function chunk(array $array, int $size): array
    {
        return array_chunk($array, $size);
    }

    public static function collapse(array $array): array
    {
        $results = [];
        foreach ($array as $values) {
            if (is_array($values)) {
                $results = array_merge($results, $values);
            }
        }
        return $results;
    }

    public static function crossJoin(array $first, array ...$arrays): array
    {
        $results = [[]];
        foreach ($first as $value) {
            $results = self::mergeCross($results, [$value], $arrays);
        }
        return $results;
    }

    protected static function mergeCross(array $results, array $values, array $arrays): array
    {
        if (empty($arrays)) {
            foreach ($values as $value) {
                $results[] = array_merge($results[0] ?? [], [$value]);
            }
            return $results;
        }

        $next = array_shift($arrays);
        $newResults = [];
        foreach ($results as $result) {
            foreach ($next as $value) {
                $newResults[] = array_merge($result, [$value]);
            }
        }

        return self::mergeCross($newResults, [], $arrays);
    }

    public static function divide(array $array): array
    {
        return [array_keys($array), array_values($array)];
    }

    public static function dot(array $array, string $prepend = ''): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            $fullKey = $prepend === '' ? $key : $prepend . '.' . $key;
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, self::dot($value, $fullKey));
            } else {
                $results[$fullKey] = $value;
            }
        }
        return $results;
    }

    public static function fill(array $array, $value): array
    {
        return array_fill(0, count($array), $value);
    }

    public static function random(array $array, int $count = 1)
    {
        if ($count === 1) {
            return $array[array_rand($array)];
        }

        $keys = array_rand($array, min($count, count($array)));
        return is_array($keys) ? array_intersect_key($array, array_flip($keys)) : $array[$keys];
    }
}
