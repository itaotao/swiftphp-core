<?php

namespace SwiftPHP\Core\Exception;

use Exception;
use Throwable;
use SwiftPHP\Core\Request\Request;

class Handler
{
    protected static $debug = false;
    protected static $renderCallback = null;
    protected static $exceptionCallback = null;
    protected static $ignoreCodes = [404, 403];
    protected static $customErrorPages = [];
    protected static $viewPath = '';

    public static function init(bool $debug = false): void
    {
        self::$debug = $debug;
        self::$viewPath = dirname(__DIR__) . '/View/';
        self::loadCustomErrorPages();
        set_exception_handler([self::class, 'handle']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    protected static function loadCustomErrorPages(): void
    {
        $errorConfigFile = dirname(__DIR__, 2) . '/config/error.php';
        if (file_exists($errorConfigFile)) {
            $config = include $errorConfigFile;
            self::$customErrorPages = $config['error_pages'] ?? [];
        }
    }

    public static function setDebug(bool $debug): void
    {
        self::$debug = $debug;
    }

    public static function handle(Throwable $e): void
    {
        if (self::$exceptionCallback) {
            call_user_func(self::$exceptionCallback, $e);
            return;
        }

        $exception = self::render($e);

        if (php_sapi_name() === 'cli') {
            echo self::formatCliOutput($exception) . PHP_EOL;
        } else {
            http_response_code($exception['status_code']);
            header('Content-Type: text/html; charset=utf-8');
            echo $exception['content'];
        }

        // 不需要 exit，让 Worker 继续处理其他请求
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // 抛出异常，让 set_exception_handler 捕获并处理
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $e = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            self::handle($e);
        }
    }

    protected static function formatCliOutput(array $exception): string
    {
        if (self::$debug) {
            $trace = is_array($exception['trace'] ?? null)
                ? implode("\n", $exception['trace'])
                : ($exception['trace'] ?? '');
            return sprintf(
                "[%s] %s in %s on line %d\n%s",
                $exception['status_code'],
                $exception['message'],
                $exception['file'],
                $exception['line'],
                $trace
            );
        }

        return sprintf("[%s] %s", $exception['status_code'], $exception['message']);
    }

    public static function render(Throwable $e, ?Request $request = null): array
    {
        $statusCode = self::getStatusCode($e);
        $message = self::getMessage($e);

        $content = self::renderErrorPage($statusCode, $message, $e, $request);

        if (self::$debug && self::$renderCallback === null) {
            return [
                'status_code' => $statusCode,
                'message' => $message,
                'error' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
                'content' => $content,
            ];
        }

        return [
            'status_code' => $statusCode,
            'message' => $message,
            'content' => $content,
        ];
    }

    protected static function renderErrorPage(
        int $statusCode,
        string $message,
        ?Throwable $e = null,
        ?Request $request = null
    ): string {
        if (self::$renderCallback) {
            return call_user_func(self::$renderCallback, $statusCode, $message, $e);
        }

        $customPage = self::$customErrorPages[$statusCode] ?? null;
        if ($customPage) {
            if (is_callable($customPage)) {
                return call_user_func($customPage, $statusCode, $message, $e);
            }
            $customPath = dirname(__DIR__, 2) . $customPage;
            if (file_exists($customPath)) {
                return self::includeCustomTemplate($customPath, $statusCode, $message, $e, $request);
            }
        }

        return self::getBuiltInErrorPage($statusCode, $message, $e, $request);
    }

    protected static function includeCustomTemplate(
        string $path,
        int $statusCode,
        string $message,
        ?Throwable $e,
        ?Request $request
    ): string {
        $debug = self::$debug;
        $errorClass = $e ? get_class($e) : '';
        $errorFile = $e ? $e->getFile() : '';
        $errorLine = $e ? $e->getLine() : 0;
        $trace = $e ? explode("\n", $e->getTraceAsString()) : [];
        $traceHtml = $e ? nl2br(htmlspecialchars(implode("\n", $trace))) : '';

        ob_start();
        include $path;
        return ob_get_clean();
    }

    protected static function getBuiltInErrorPage(
        int $statusCode,
        string $message,
        ?Throwable $e,
        ?Request $request
    ): string {
        $errorFile = self::$viewPath . 'error_' . $statusCode . '.html';

        if (!file_exists($errorFile)) {
            $errorFile = self::$viewPath . 'error_500.html';
            $statusCode = 500;
        }

        return self::includeCustomTemplate($errorFile, $statusCode, $message, $e, $request);
    }

    protected static function getStatusCode(Throwable $e): int
    {
        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        return 500;
    }

    protected static function getMessage(Throwable $e): string
    {
        if (self::$debug) {
            return $e->getMessage();
        }

        $statusCode = self::getStatusCode($e);

        $messages = [
            400 => 'Bad Request - 请求错误',
            401 => 'Unauthorized - 未授权',
            402 => 'Payment Required - 支付需要',
            403 => 'Forbidden - 禁止访问',
            404 => 'Not Found - 页面不存在',
            405 => 'Method Not Allowed - 方法不允许',
            406 => 'Not Acceptable - 不可接受',
            407 => 'Proxy Authentication Required - 代理认证需要',
            408 => 'Request Timeout - 请求超时',
            409 => 'Conflict - 冲突',
            410 => 'Gone - 已删除',
            411 => 'Length Required - 需要长度',
            412 => 'Precondition Failed - 前置条件失败',
            413 => 'Payload Too Large - 负载过大',
            414 => 'URI Too Long - URI 过长',
            415 => 'Unsupported Media Type - 不支持的媒体类型',
            416 => 'Range Not Satisfiable - 范围不可满足',
            417 => 'Expectation Failed - 期望失败',
            418 => 'Im a teapot - 我是一个茶壶',
            421 => 'Misdirected Request - 请求方向错误',
            422 => 'Unprocessable Entity - 无法处理的实体',
            423 => 'Locked - 已锁定',
            424 => 'Failed Dependency - 依赖失败',
            426 => 'Upgrade Required - 需要升级',
            428 => 'Precondition Required - 需要前置条件',
            429 => 'Too Many Requests - 请求过多',
            431 => 'Request Header Fields Too Large - 请求头字段过大',
            451 => 'Unavailable For Legal Reasons - 因法律原因不可用',
            500 => 'Internal Server Error - 服务器内部错误',
            501 => 'Not Implemented - 未实现',
            502 => 'Bad Gateway - 网关错误',
            503 => 'Service Unavailable - 服务不可用',
            504 => 'Gateway Timeout - 网关超时',
            505 => 'HTTP Version Not Supported - HTTP 版本不支持',
            506 => 'Variant Also Negotiates - 变体也协商',
            507 => 'Insufficient Storage - 存储不足',
            508 => 'Loop Detected - 检测到循环',
            510 => 'Not Extended - 未扩展',
            511 => 'Network Authentication Required - 需要网络认证',
        ];

        return $messages[$statusCode] ?? '服务器错误';
    }

    public static function setRenderCallback(callable $callback): void
    {
        self::$renderCallback = $callback;
    }

    public static function setExceptionCallback(callable $callback): void
    {
        self::$exceptionCallback = $callback;
    }

    public static function setIgnoreCodes(array $codes): void
    {
        self::$ignoreCodes = $codes;
    }

    public static function setCustomErrorPages(array $pages): void
    {
        self::$customErrorPages = $pages;
    }

    public static function abort(int $statusCode, string $message = ''): void
    {
        throw new HttpException($statusCode, $message);
    }

    public static function badRequest(string $message = 'Bad Request'): void
    {
        self::abort(400, $message);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::abort(401, $message);
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::abort(403, $message);
    }

    public static function notFound(string $message = 'Not Found'): void
    {
        self::abort(404, $message);
    }

    public static function methodNotAllowed(string $message = 'Method Not Allowed'): void
    {
        self::abort(405, $message);
    }

    public static function validationError(string $message = 'Validation Error'): void
    {
        self::abort(422, $message);
    }

    public static function serverError(string $message = 'Internal Server Error'): void
    {
        self::abort(500, $message);
    }
}