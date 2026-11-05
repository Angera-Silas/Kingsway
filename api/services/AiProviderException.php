<?php

namespace App\API\Services;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly bool $fallbackAllowed = false
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function allowsProviderFallback(): bool
    {
        return $this->fallbackAllowed;
    }
}
