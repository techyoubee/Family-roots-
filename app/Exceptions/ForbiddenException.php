<?php

namespace App\Exceptions;

final class ForbiddenException extends HttpException
{
    public function __construct($message = 'You are not allowed to access this resource.')
    {
        parent::__construct(403, $message);
    }
}
