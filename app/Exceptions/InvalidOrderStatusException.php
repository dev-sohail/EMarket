<?php

namespace App\Exceptions;

class InvalidOrderStatusException extends \Exception
{
    public function __construct(string $message = 'Invalid order status', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
