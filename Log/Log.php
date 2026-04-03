<?php

namespace SwiftPHP\Log;

class Log
{
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    protected static $logPath;
    protected static $logLevel = self::DEBUG;
    protected static $single = false;
    protected static $singlePath;
    protected static $allowLevels = [];
    protected static $format = '[{time}] [{level}] {message}';
    protected static $fileSuffix = 'log';

    public static function init(array $config = []): void
    {
        self::$logPath = $config['path'] ?? \SwiftPHP\Path\Path::getRootPath() . '/runtime/log';
        self::$logLevel = $config['level'] ?? self::DEBUG;
        self::$single = $config['single'] ?? false;
        self::$singlePath = $config['single_path'] ?? '';
        self::$allowLevels = $config['allow_level'] ?? [];
        self::$format = $config['format'] ?? self::$format;
        self::$fileSuffix = $config['file_suffix'] ?? 'log';

        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
    }

    public static function log(string $level, $message, array $context = []): void
    {
        if (!self::isLogLevelEnabled($level)) {
            return;
        }

        $message = self::interpolate($message, $context);
        $logFile = self::getLogFile($level);
        $time = date('Y-m-d H:i:s');
        $level = strtoupper($level);

        $format = self::$format;
        $format = str_replace('{time}', $time, $format);
        $format = str_replace('{level}', $level, $format);
        $format = str_replace('{message}', $message, $format);

        file_put_contents($logFile, $format . PHP_EOL, FILE_APPEND);
    }

    public static function emergency($message, array $context = []): void
    {
        self::log(self::EMERGENCY, $message, $context);
    }

    public static function alert($message, array $context = []): void
    {
        self::log(self::ALERT, $message, $context);
    }

    public static function critical($message, array $context = []): void
    {
        self::log(self::CRITICAL, $message, $context);
    }

    public static function error($message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    public static function warning($message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    public static function notice($message, array $context = []): void
    {
        self::log(self::NOTICE, $message, $context);
    }

    public static function info($message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    public static function debug($message, array $context = []): void
    {
        self::log(self::DEBUG, $message, $context);
    }

    protected static function isLogLevelEnabled(string $level): bool
    {
        $levels = [
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7,
        ];

        $configLevel = $levels[self::$logLevel] ?? 7;

        if (isset($levels[$level])) {
            return $levels[$level] <= $configLevel;
        }

        return true;
    }

    protected static function getLogFile(string $level): string
    {
        if (self::$single) {
            $path = self::$singlePath ?: self::$logPath . '/single.' . self::$fileSuffix;
            return $path;
        }

        $date = date('Y-m-d');
        $filename = $level . '_' . $date . '.' . self::$fileSuffix;
        return self::$logPath . '/' . $filename;
    }

    protected static function interpolate($message, array $context = []): string
    {
        if (!is_string($message)) {
            $message = var_export($message, true);
        }

        if (empty($context)) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }

    public static function clear(?string $level = null): void
    {
        if ($level) {
            $logFile = self::getLogFile($level);
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        } else {
            $files = glob(self::$logPath . '/*.' . self::$fileSuffix);
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
