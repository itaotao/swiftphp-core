<?php

namespace SwiftPHP\Json;

class Json
{
    const CODE_SUCCESS = 200;
    const CODE_CREATED = 201;
    const CODE_NO_CONTENT = 204;
    const CODE_BAD_REQUEST = 400;
    const CODE_UNAUTHORIZED = 401;
    const CODE_FORBIDDEN = 403;
    const CODE_NOT_FOUND = 404;
    const CODE_METHOD_NOT_ALLOWED = 405;
    const CODE_VALIDATION_ERROR = 422;
    const CODE_SERVER_ERROR = 500;

    protected static $debug = false;
    protected static $debugData = [];

    public static function init(bool $debug = false): void
    {
        self::$debug = $debug;
    }

    public static function success($data = [], string $msg = 'success', int $code = self::CODE_SUCCESS): \SwiftPHP\Response\Response
    {
        $response = [
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ];

        if (self::$debug) {
            $response['debug'] = self::$debugData;
        }

        return \SwiftPHP\Response\Response::json($response, $code);
    }

    public static function created($data = [], string $msg = 'Created'): \SwiftPHP\Response\Response
    {
        return self::success($data, $msg, self::CODE_CREATED);
    }

    public static function noContent(string $msg = 'No Content'): \SwiftPHP\Response\Response
    {
        return self::success([], $msg, self::CODE_NO_CONTENT);
    }

    public static function error(string $msg = 'Error', int $code = self::CODE_BAD_REQUEST, $errors = null): \SwiftPHP\Response\Response
    {
        $response = [
            'code' => $code,
            'msg' => $msg,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        if (self::$debug) {
            $response['debug'] = self::$debugData;
        }

        return \SwiftPHP\Response\Response::json($response, $code);
    }

    public static function unauthorized(string $msg = 'Unauthorized'): \SwiftPHP\Response\Response
    {
        return self::error($msg, self::CODE_UNAUTHORIZED);
    }

    public static function forbidden(string $msg = 'Forbidden'): \SwiftPHP\Response\Response
    {
        return self::error($msg, self::CODE_FORBIDDEN);
    }

    public static function notFound(string $msg = 'Not Found'): \SwiftPHP\Response\Response
    {
        return self::error($msg, self::CODE_NOT_FOUND);
    }

    public static function validationError($errors, string $msg = 'Validation Error'): \SwiftPHP\Response\Response
    {
        return self::error($msg, self::CODE_VALIDATION_ERROR, $errors);
    }

    public static function serverError(string $msg = 'Internal Server Error'): \SwiftPHP\Response\Response
    {
        return self::error($msg, self::CODE_SERVER_ERROR);
    }

    public static function paginate(\SwiftPHP\Paginate\Paginate $paginator, string $msg = 'success'): \SwiftPHP\Response\Response
    {
        return self::success($paginator->toArray(), $msg);
    }

    public static function list($data, string $msg = 'success'): \SwiftPHP\Response\Response
    {
        return self::success([
            'list' => $data,
            'count' => count($data),
        ], $msg);
    }

    public static function debug(array $data = []): self
    {
        self::$debugData = $data;
        return new self();
    }

    public static function with(string $key, $value): self
    {
        self::$debugData[$key] = $value;
        return new self();
    }

    public static function isDebug(): bool
    {
        return self::$debug;
    }

    public static function setDebug(bool $debug): void
    {
        self::$debug = $debug;
    }

    public static function clearDebug(): void
    {
        self::$debugData = [];
    }

    public static function code(int $code): \SwiftPHP\Response\Response
    {
        return self::error(self::getCodeMessage($code), $code);
    }

    protected static function getCodeMessage(int $code): string
    {
        $messages = [
            self::CODE_SUCCESS => 'Success',
            self::CODE_CREATED => 'Created',
            self::CODE_NO_CONTENT => 'No Content',
            self::CODE_BAD_REQUEST => 'Bad Request',
            self::CODE_UNAUTHORIZED => 'Unauthorized',
            self::CODE_FORBIDDEN => 'Forbidden',
            self::CODE_NOT_FOUND => 'Not Found',
            self::CODE_METHOD_NOT_ALLOWED => 'Method Not Allowed',
            self::CODE_VALIDATION_ERROR => 'Validation Error',
            self::CODE_SERVER_ERROR => 'Internal Server Error',
        ];

        return $messages[$code] ?? 'Unknown Error';
    }
}
