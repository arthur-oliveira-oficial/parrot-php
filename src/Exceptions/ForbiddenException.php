<?php

declare(strict_types=1);

namespace App\Exceptions;

class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Acesso proibido', ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}
