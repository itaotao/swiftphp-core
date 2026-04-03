<?php

namespace SwiftPHP\Container;

class Container
{
    protected static $instances = [];
    protected static $bindings = [];

    public static function get(string $abstract, array $params = [])
    {
        if (isset(self::$instances[$abstract])) {
            return self::$instances[$abstract];
        }

        if (isset(self::$bindings[$abstract])) {
            $concrete = self::$bindings[$abstract];
            if (is_callable($concrete)) {
                return $concrete();
            }
            if (is_string($concrete) && class_exists($concrete)) {
                return self::resolve($concrete, $params);
            }
        }

        if (class_exists($abstract)) {
            return self::resolve($abstract, $params);
        }

        return null;
    }

    public static function set(string $abstract, $concrete): void
    {
        self::$bindings[$abstract] = $concrete;
    }

    public static function has(string $abstract): bool
    {
        return isset(self::$instances[$abstract]) || isset(self::$bindings[$abstract]);
    }

    public static function singleton(string $abstract, $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        self::$instances[$abstract] = self::get($abstract);
    }

    public static function resolve(string $class, array $params = [])
    {
        $reflector = new \ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class {$class} is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = self::resolveDependencies($constructor->getParameters());

        return $reflector->newInstanceArgs($dependencies);
    }

    protected static function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
                $className = $type->getName();
                if (class_exists($className)) {
                    $dependencies[] = self::get($className);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                $dependencies[] = null;
            }
        }

        return $dependencies;
    }

    public static function make(string $abstract, array $params = []): mixed
    {
        return self::get($abstract, $params);
    }

    public static function delete(string $abstract): void
    {
        unset(self::$instances[$abstract], self::$bindings[$abstract]);
    }

    public static function clear(): void
    {
        self::$instances = [];
        self::$bindings = [];
    }
}
