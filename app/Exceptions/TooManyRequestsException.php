<?php

namespace App\Exceptions;

final class TooManyRequestsException extends HttpException
{
    public function __construct($message = 'Too many attempts. Please wait a moment and try again.', $retryAfter = 60)
    {
        parent::__construct(429, $message, ['Retry-After' => (string) max(1, (int) $retryAfter)]);
    }
}
