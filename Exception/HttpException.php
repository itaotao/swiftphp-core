<?php

namespace SwiftPHP\Exception;

use SwiftPHP\Request\Request;
use SwiftPHP\Response\Response;

class HttpException extends \Exception
{
    protected $statusCode;
    protected $message;

    public function __construct(int $statusCode, string $message = '')
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
