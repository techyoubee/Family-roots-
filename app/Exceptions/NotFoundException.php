<?php

namespace App\Exceptions;

final class NotFoundException extends HttpException
{
    public function __construct($message = 'The page or record you requested could not be found.')
    {
        parent::__construct(404, $message);
    }
}
