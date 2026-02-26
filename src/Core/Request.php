<?php

namespace App\Core;

use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Helper para criação de requisições PSR-7.
 */
class Request
{
    /**
     * Cria uma instância de ServerRequest a partir das variáveis globais do PHP.
     */
    public static function createFromGlobals(): ServerRequestInterface
    {
        return ServerRequest::fromGlobals();
    }

    /**
     * Obtém um atributo da requisição com valor padrão.
     */
    public static function getAttribute(
        ServerRequestInterface $request,
        string $name,
        mixed $default = null
    ): mixed {
        return $request->getAttribute($name, $default);
    }

    /**
     * Obtém o corpo da requisição como array (JSON ou form data).
     */
    public static function getParsedBodyArray(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        if (is_array($body)) {
            return $body;
        }

        return [];
    }

    /**
     * Obtém dados JSON do corpo da requisição.
     */
    public static function getJsonData(ServerRequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $body = (string) $request->getBody();
            $data = json_decode($body, true);

            return is_array($data) ? $data : [];
        }

        return [];
    }

    /**
     * Obtém o ID do usuário a partir do token JWT (implementação básica).
     */
    public static function getUserIdFromToken(ServerRequestInterface $request): ?int
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        // TODO: Implementar validação JWT real
        // Por agora, retorna o ID do atributo da requisição
        return $request->getAttribute('user_id');
    }
}
