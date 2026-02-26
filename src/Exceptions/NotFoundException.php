<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exceção para erro 404 - Recurso não encontrado.
 */
class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Recurso não encontrado', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
