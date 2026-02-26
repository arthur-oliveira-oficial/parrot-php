<?php

declare(strict_types=1);

/**
 * Exceção 404 - Recurso não encontrado
 *
 * Usada quando o recurso solicitado não existe.
 * @see https://developer.mozilla.org/pt-BR/docs/Web/HTTP/Status/404 404 Not Found
 */

namespace App\Exceptions;

/**
 * Exceção 404 - Recurso não encontrado
 *
 * Exemplo de uso:
 *     throw new NotFoundException('Usuário não encontrado');
 */
class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Recurso não encontrado', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
