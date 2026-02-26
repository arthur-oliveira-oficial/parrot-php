<?php

declare(strict_types=1);

namespace App\Exceptions;

class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Método não permitido', ?\Throwable $previous = null)
    {
        parent::__construct($message, 405, $previous);
    }
}
