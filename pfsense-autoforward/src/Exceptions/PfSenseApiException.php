<?php

namespace Chr0mX\PfSenseAutoForward\Exceptions;

use RuntimeException;

class PfSenseApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $responseBody = '',
    ) {
        parent::__construct($message);
    }
}
