<?php

declare(strict_types=1);

namespace App\Exceptions;

class HttpException extends \Exception
{
    private int $statusCode;
    private array $headers;

    public function __construct(
        string $message = '',
        int $statusCode = 500,
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
