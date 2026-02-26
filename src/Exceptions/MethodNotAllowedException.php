<?php

declare(strict_types=1);

/**
 * Exceção 405 - Método não permitido
 *
 * Usada quando o método HTTP não é permitido para a rota.
 * Exemplo: PUT /api/usuarios quando só允许 GET e POST.
 * @see https://developer.mozilla.org/pt-BR/docs/Web/HTTP/Status/405 405 Method Not Allowed
 */

namespace App\Exceptions;

/**
 * Exceção 405 - Método não permitido
 */
class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Método não permitido', ?\Throwable $previous = null)
    {
        parent::__construct($message, 405, $previous);
    }
}
