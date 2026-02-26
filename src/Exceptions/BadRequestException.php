<?php

declare(strict_types=1);

/**
 * Exceção 400 - Requisição inválida
 *
 * Usada quando o servidor não pode processar a requisição
 * devido a erro de sintaxe ou dados inválidos.
 * @see https://developer.mozilla.org/pt-BR/docs/Web/HTTP/Status/400 400 Bad Request
 */

namespace App\Exceptions;

/**
 * Exceção 400 - Requisição inválida
 */
class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Requisição inválida', ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, $previous);
    }
}
