<?php

declare(strict_types=1);

namespace App\Exceptions;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Recurso não encontrado', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
