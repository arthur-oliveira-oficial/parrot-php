<?php

declare(strict_types=1);

/**
 * Exceção 401 - Não autorizado
 *
 * Usada quando a autenticação é necessária ou falhou.
 * @see https://developer.mozilla.org/pt-BR/docs/Web/HTTP/Status/401 401 Unauthorized
 */

namespace App\Exceptions;

/**
 * Exceção 401 - Não autorizado
 *
 * Exemplo: token JWT inválido ou expirado
 */
class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Não autorizado', ?\Throwable $previous = null)
    {
        parent::__construct($message, 401, $previous);
    }
}
