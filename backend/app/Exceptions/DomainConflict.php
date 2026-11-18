<?php

namespace App\Exceptions;

use RuntimeException;

class DomainConflict extends RuntimeException
{
    public function __construct(public string $errorCode, string $message, public int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}
