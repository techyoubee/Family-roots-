<?php

namespace App\Exceptions;

class ValidationException extends HttpException
{
    /** @var array */
    protected $errors;

    public function __construct(array $errors, $message = 'The submitted data is invalid.')
    {
        parent::__construct(422, $message, [], ['errors' => $errors]);
        $this->errors = $errors;
    }

    public function errors()
    {
        return $this->errors;
    }
}
