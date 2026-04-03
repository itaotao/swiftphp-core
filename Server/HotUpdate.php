<?php

namespace SwiftPHP\Core\Server;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Workerman\Worker;

class HotUpdate
{
    protected $watchDirs = [];
    protected $timeCache = [];

    public function __construct()
    {
        $basePath = dirname(__DIR__, 2);
        $this->watchDirs = [
            $basePath . '/app',
            $basePath . '/config',
            $basePath . '/core',
        ];
    }

    protected function scanFiles(): bool
    {
        $changed = false;
        foreach ($this->watchDirs as $dir) {
            if (!is_dir($dir)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir)
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) continue;
                if ($file->getExtension() !== 'php') continue;

                $path = $file->getPathname();
                $time = filemtime($path);

                if (!isset($this->timeCache[$path])) {
                    $this->timeCache[$path] = $time;
                } elseif ($this->timeCache[$path] !== $time) {
                    $this->timeCache[$path] = $time;
                    $changed = true;
                }
            }
        }
        return $changed;
    }

    public function register(): void
    {
        $this->scanFiles();

        \Workerman\Timer::add(1, function () {
            if ($this->scanFiles()) {
                echo "[HotUpdate] 文件修改，正在重载..." . PHP_EOL;
                if (DIRECTORY_SEPARATOR === '\\') {
                    exit; // Windows 只能退出
                } else {
                    Worker::reloadAllWorkers(); // Linux 正常 reload
                }
            }
        });
    }
}