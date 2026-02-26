<?php

declare(strict_types=1);

/**
 * Exceção 403 - Acesso proibido
 *
 * Usada quando o usuário está autenticado mas não tem permissão.
 * @see https://developer.mozilla.org/pt-BR/docs/Web/HTTP/Status/403 403 Forbidden
 */

namespace App\Exceptions;

/**
 * Exceção 403 - Acesso proibido
 *
 * Exemplo: usuário tentando acessar recurso de outro usuário
 */
class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Acesso proibido', ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}
