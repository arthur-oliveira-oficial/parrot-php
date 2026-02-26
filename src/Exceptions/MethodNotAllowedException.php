<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exceção para erro 405 - Método não permitido.
 */
class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Método não permitido', ?\Throwable $previous = null)
    {
        parent::__construct($message, 405, $previous);
    }
}
