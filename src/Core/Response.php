<?php

/**
 * Parrot PHP Framework - Response Helper
 *
 * Factory de respostas HTTP PSR-7.
 * Fornece métodos estáticos convenientes para criar respostas comuns.
 *
 * Esta classe abstrai a criação de objetos Response PSR-7,
 * facilitando a retorno de JSON e erros HTTP comuns.
 *
 * @see https://www.php-fig.org/psr/psr-7/ PSR-7: HTTP Message Interfaces
 * @see https://github.com/Nyholm/psr7 Nyholm PSR-7 implementation
 */

namespace App\Core;

use Nyholm\Psr7\Response as PsrResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Helper para criar respostas HTTP
 *
 * Métodos estáticos para criar respostas comuns:
 * - json() - resposta JSON genérica
 * - ok() - sucesso (200)
 * - created() - criado (201)
 * - notFound() - não encontrado (404)
 * - unauthorized() - não autorizado (401)
 * - forbidden() - proibido (403)
 * - serverError() - erro interno (500)
 * - tooManyRequests() - rate limit (429)
 * - noContent() - sem conteúdo (204)
 *
 * @package App\Core
 */
class Response
{
    /**
     * Cria uma resposta JSON
     *
     * Método base usado por todos os outros métodos de resposta.
     * O dados são codificados em JSON com suporte a Unicode.
     *
     * @param mixed $data Dados a serem convertidos em JSON
     * @param int $statusCode Código de status HTTP (200, 404, etc.)
     * @param array $headers Headers adicionais
     * @return ResponseInterface Resposta PSR-7
     */
    public static function json(
        mixed $data,
        int $statusCode = 200,
        array $headers = []
    ): ResponseInterface {
        $defaultHeaders = ['Content-Type' => 'application/json'];
        $headers = array_merge($defaultHeaders, $headers);

        return new PsrResponse(
            $statusCode,
            $headers,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Cria uma resposta de erro
     *
     * O erro é retornado no formato JSON: {"error": "mensagem", ...}
     *
     * @param string $message Mensagem de erro
     * @param int $statusCode Código de erro HTTP (padrão: 400 Bad Request)
     * @param array $extra Dados adicionais para incluir na resposta
     * @return ResponseInterface Resposta PSR-7
     */
    public static function error(
        string $message,
        int $statusCode = 400,
        array $extra = []
    ): ResponseInterface {
        $data = array_merge(['error' => $message], $extra);

        return self::json($data, $statusCode);
    }

    /**
     * Resposta de sucesso (HTTP 200 OK)
     *
     * @param mixed $data Dados a retornar em JSON
     * @return ResponseInterface Resposta com status 200
     */
    public static function ok(mixed $data): ResponseInterface
    {
        return self::json($data, 200);
    }

    /**
     * Resposta de criação bem-sucedida (HTTP 201 Created)
     *
     * Usado quando um novo recurso é criado no servidor.
     *
     * @param mixed $data Dados do recurso criado (opcional)
     * @return ResponseInterface Resposta com status 201
     */
    public static function created(mixed $data = null): ResponseInterface
    {
        return self::json($data ?? ['message' => 'Created'], 201);
    }

    /**
     * Resposta de não encontrado (HTTP 404)
     *
     * Usado quando o recurso solicitado não existe.
     *
     * @param string $message Mensagem de erro
     * @return ResponseInterface Resposta com status 404
     */
    public static function notFound(string $message = 'Not found'): ResponseInterface
    {
        return self::error($message, 404);
    }

    /**
     * Resposta de não autorizado (HTTP 401)
     *
     * Usado quando a autenticação é necessária ou falhou.
     *
     * @param string $message Mensagem de erro
     * @return ResponseInterface Resposta com status 401
     */
    public static function unauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return self::error($message, 401);
    }

    /**
     * Resposta de proibido (HTTP 403)
     *
     * Usado quando o usuário está autenticado mas não tem permissão.
     *
     * @param string $message Mensagem de erro
     * @return ResponseInterface Resposta com status 403
     */
    public static function forbidden(string $message = 'Forbidden'): ResponseInterface
    {
        return self::error($message, 403);
    }

    /**
     * Resposta de erro interno do servidor (HTTP 500)
     *
     * Usado para erros inesperados no servidor.
     *
     * @param string $message Mensagem de erro
     * @return ResponseInterface Resposta com status 500
     */
    public static function serverError(string $message = 'Internal Server Error'): ResponseInterface
    {
        return self::error($message, 500);
    }

    /**
     * Resposta de muitas requisições (HTTP 429 - Rate Limit)
     *
     * Usado pelo RateLimitMiddleware quando o cliente excede o limite.
     *
     * @param string $message Mensagem de erro
     * @param int $retryAfter Segundos até poder tentar novamente
     * @return ResponseInterface Resposta com status 429
     */
    public static function tooManyRequests(string $message = 'Too Many Requests', int $retryAfter = 60): ResponseInterface
    {
        return self::error($message, 429, ['retry_after' => $retryAfter])
            ->withHeader('Retry-After', (string) $retryAfter);
    }

    /**
     * Resposta sem conteúdo (HTTP 204 No Content)
     *
     * Usado quando a operação foi bem-sucedida mas não há dados para retornar.
     * Comum em operações de DELETE.
     *
     * @return ResponseInterface Resposta com status 204
     */
    public static function noContent(): ResponseInterface
    {
        return new PsrResponse(204);
    }
}
