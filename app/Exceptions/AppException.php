<?php

namespace App\Exceptions;

use RuntimeException;

class AppException extends RuntimeException
{
    /** @var array */
    protected $context = [];

    public function __construct($message = '', array $context = [], $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function context()
    {
        return $this->context;
    }
}
