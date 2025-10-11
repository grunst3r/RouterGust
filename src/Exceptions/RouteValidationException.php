<?php

namespace GustRouter\Exceptions;

class RouteValidationException extends \Exception
{
    public function __construct(string $message = "Route validation failed", int $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}