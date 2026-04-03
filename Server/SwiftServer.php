<?php

namespace SwiftPHP\Server;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;

class SwiftServer
{
    protected $worker;
    protected $config = [];
    protected $port = 8787;
    protected $processes = null;
    protected $hotReloadEnabled = false;

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $rootPath = \SwiftPHP\Path\Path::getRootPath();
        $configFile = $rootPath . '/config/server.php';
        if (file_exists($configFile)) {
            $this->config = include $configFile;
        }
        $this->port = $this->config['server']['port'] ?? 8787;
        $this->processes = $this->config['server']['processes'] ?? null;

        $isDebug = $this->config['app']['debug'] ?? false;
        $hotReloadConfig = $this->config['hot_reload'] ?? [];
        $this->hotReloadEnabled = $isDebug || ($hotReloadConfig['enable'] ?? false);
    }

    public function start(): void
    {
        $this->worker = new Worker("http://0.0.0.0:{$this->port}");

        // ==============================
        // Windows 强制只能开 1 个进程！
        // ==============================
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->worker->count = 1; // Windows 强制单进程
        } else {
            $this->worker->count = $this->getCpuCount();
        }
        $this->worker->name = 'SwiftPHP';
        $this->worker->onMessage = [$this, 'handleRequest'];
        $this->worker->onWorkerStart = [$this, 'onWorkerStart'];
        $this->worker->onWorkerStop = [$this, 'onWorkerStop'];

        Worker::runAll();
    }

    protected function getCpuCount()
    {
        return trim(shell_exec('grep -c ^processor /proc/cpuinfo 2>/dev/null || echo 2'));
    }

    public function onWorkerStart(Worker $worker): void
    {
        $this->initFramework();

        if ($this->hotReloadEnabled) {
            require_once __DIR__ . '/HotUpdate.php';
            (new \SwiftPHP\Server\HotUpdate())->register();
        }
    }

    public function onWorkerStop(Worker $worker): void
    {
    }

    protected function initFramework(): void
    {
        $rootPath = \SwiftPHP\Path\Path::getRootPath();
        require_once $rootPath . '/app/common.php';

        $debug = ($this->config['app']['debug'] ?? false);
        \SwiftPHP\Exception\Handler::init($debug);
    }

    public function handleRequest(TcpConnection $connection, $data): void
    {
        try {
            $rootPath = \SwiftPHP\Path\Path::getRootPath();
            $i18nConfig = [];
            $configFile = $rootPath . '/config/i18n.php';
            if (file_exists($configFile)) {
                $i18nConfig = include $configFile;
            }
            \SwiftPHP\I18n\I18n::init($i18nConfig);

            $request = new \SwiftPHP\Request\Request($data);

            // 检测并设置语言
            $detectedLocale = \SwiftPHP\I18n\I18n::detectLocale($request->get(), $request->header() ?: []);
            \SwiftPHP\I18n\I18n::setLocale($detectedLocale);

            $router = new \SwiftPHP\Routing\Router();
            $response = $router->dispatch($request);

            $connection->send($response);
        } catch (\Throwable $e) {
            $exception = \SwiftPHP\Exception\Handler::render($e);
            $errorResponse = new \SwiftPHP\Response\Response(
                $exception['status_code'],
                ['Content-Type' => 'text/html; charset=utf-8'],
                $exception['content']
            );
            $connection->send($errorResponse);
        }
    }

    public function stop(): void
    {
        Worker::stopAll();
    }

    public function reload(): void
    {
        Worker::reloadAllWorkers();
    }
}