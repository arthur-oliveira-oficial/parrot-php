<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - HTTP Exception
 *
 * Exceção base para erros HTTP.
 * Permite definir:
 * - Código de status HTTP (404, 500, etc.)
 * - Mensagem de erro
 * - Headers adicionais
 *
 * As subclasses representam erros HTTP específicos.
 *
 * @see https://developer.mozilla.org/pt-BR/docs/Web/HTTP/Status HTTP Status Codes
 */

namespace App\Exceptions;

/**
 * Exceção HTTP
 *
 * Exceção para erros HTTP com código de status e headers.
 * Usada pelo ErrorHandlerMiddleware para formatar respostas de erro.
 */
class HttpException extends \Exception
{
    /** @var int Código de status HTTP (404, 500, etc.) */
    private int $statusCode;
    private array $headers;

    /**
     * Construtor
     *
     * @param string $message Mensagem de erro
     * @param int $statusCode Código HTTP
     * @param \Throwable|null $previous Exceção anterior
     * @param array $headers Headers adicionais
     */
    public function __construct(
        string $message = '',
        int $statusCode = 500,
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
