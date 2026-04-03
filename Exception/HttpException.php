<?php

namespace SwiftPHP\Core\Exception;

use SwiftPHP\Core\Request\Request;
use SwiftPHP\Core\Response\Response;

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
