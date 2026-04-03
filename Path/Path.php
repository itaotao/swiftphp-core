<?php

namespace SwiftPHP\Path;

class Path
{
    protected static $rootPath = null;

    public static function getRootPath(): string
    {
        if (defined('SWIFTPHP_ROOT')) {
            return SWIFTPHP_ROOT;
        }

        if (self::$rootPath !== null) {
            return self::$rootPath;
        }

        $vendorDir = self::findVendorDir();
        if ($vendorDir) {
            self::$rootPath = dirname($vendorDir);
            return self::$rootPath;
        }

        self::$rootPath = dirname(__DIR__, 3);
        return self::$rootPath;
    }

    public static function setRootPath(string $path): void
    {
        self::$rootPath = rtrim($path, DIRECTORY_SEPARATOR);
    }

    protected static function findVendorDir(): ?string
    {
        $dir = __DIR__;

        for ($i = 0; $i < 10; $i++) {
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }

            if (is_dir($dir . '/vendor') && file_exists($dir . '/composer.json')) {
                return $dir . '/vendor';
            }

            $vendorInParent = $parent . '/vendor';
            if (is_dir($vendorInParent)) {
                $vendorComposer = $vendorInParent . '/composer/autoload.php';
                if (file_exists($vendorComposer)) {
                    return $vendorInParent;
                }
            }

            if (is_dir($parent . '/vendor')) {
                return $parent . '/vendor';
            }

            $dir = $parent;
        }

        $current = dirname(__DIR__);
        for ($i = 0; $i < 5; $i++) {
            $vendor = $current . '/vendor';
            if (is_dir($vendor)) {
                return $vendor;
            }
            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }

        return null;
    }

    public static function getAppPath(): string
    {
        return self::getRootPath() . '/app';
    }

    public static function getConfigPath(): string
    {
        return self::getRootPath() . '/config';
    }

    public static function getRuntimePath(): string
    {
        return self::getRootPath() . '/runtime';
    }

    public static function getPublicPath(): string
    {
        return self::getRootPath() . '/public';
    }
}