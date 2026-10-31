<?php

namespace App\Exceptions;

class InvalidOrderTransitionException extends \Exception
{
    public function __construct(string $message = 'Invalid order status transition', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
