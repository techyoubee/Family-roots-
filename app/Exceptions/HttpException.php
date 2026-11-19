<?php

namespace App\Exceptions;

/**
 * An exception that carries the HTTP status + safe user-facing message shown
 * on the error page. Internal details are only rendered when APP_DEBUG=true.
 */
class HttpException extends AppException
{
    /** @var int */
    protected $status;

    /** @var array */
    protected $headers;

    public function __construct($status, $message = '', array $headers = [], array $context = [], \Throwable $previous = null)
    {
        parent::__construct($message, $context, $status, $previous);
        $this->status = (int) $status;
        $this->headers = $headers;
    }

    public function status()
    {
        return $this->status;
    }

    public function headers()
    {
        return $this->headers;
    }
}
