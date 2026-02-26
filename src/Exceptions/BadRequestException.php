<?php

declare(strict_types=1);

namespace App\Exceptions;

class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Requisição inválida', ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, $previous);
    }
}
