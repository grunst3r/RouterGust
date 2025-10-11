<?php

namespace GustRouter\Exceptions;

class RouteNotFoundException extends \Exception
{
    public function __construct(string $message = "Route not found", int $code = 404, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}