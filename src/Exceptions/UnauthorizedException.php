<?php

declare(strict_types=1);

namespace App\Exceptions;

class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Não autorizado', ?\Throwable $previous = null)
    {
        parent::__construct($message, 401, $previous);
    }
}
