<?php

/**
 * Parrot PHP Framework - Request Helper
 *
 * Helper estático para manipulação de requisições HTTP PSR-7.
 * Fornece métodos convenientes para extrair dados da requisição.
 *
 * @see https://www.php-fig.org/psr/psr-7/ PSR-7: HTTP Message Interfaces
 */

namespace App\Core;

use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Helper para manipulação de requisições
 *
 * Métodos estáticos para facilitar o acesso a dados comuns:
 * - getAttribute() - obtém atributos da requisição (parâmetros de rota)
 * - getParsedBodyArray() - obtém dados do formulário (POST)
 * - getJsonData() - obtém dados de requisições JSON
 * - getUserIdFromToken() - obtém ID do usuário do token JWT
 *
 * @package App\Core
 */
class Request
{
    /**
     * Cria uma requisição a partir das superglobais do PHP
     *
     * Wrapper estático para Nyholm\Psr7\ServerRequest::fromGlobals()
     *
     * @return ServerRequestInterface Requisição PSR-7
     */
    public static function createFromGlobals(): ServerRequestInterface
    {
        return ServerRequest::fromGlobals();
    }

    /**
     * Obtém um atributo da requisição
     *
     * Atributos são parâmetros capturados da URL (ex: {id})
     * ou atributos adicionados por middlewares.
     *
     * @param ServerRequestInterface $request A requisição
     * @param string $name Nome do atributo
     * @param mixed $default Valor padrão se não existir
     * @return mixed O valor do atributo ou o padrão
     */
    public static function getAttribute(
        ServerRequestInterface $request,
        string $name,
        mixed $default = null
    ): mixed {
        return $request->getAttribute($name, $default);
    }

    /**
     * Obtém os dados do corpo da requisição como array
     *
     * Útil para dados de formulário (application/x-www-form-urlencoded).
     * Retorna array vazio se não houver dados ou se não for array.
     *
     * @param ServerRequestInterface $request A requisição
     * @return array Dados do POST ou array vazio
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
     * Obtém dados JSON do corpo da requisição
     *
     * Necessário quando o cliente envia JSON no corpo da requisição
     * (Content-Type: application/json).
     *
     * O PHP não parseia automaticamente JSON no $request->getParsedBody(),
     * por isso este método é necessário.
     *
     * @param ServerRequestInterface $request A requisição
     * @return array Dados JSON decodificados ou array vazio
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
     * Obtém o ID do usuário a partir do token JWT
     *
     * Este método espera que o middleware JwtAuthMiddleware
     * já tenha validado o token e adicionado o atributo 'user_id'
     * à requisição.
     *
     * Primeiro verifica se o atributo 'user_id' já foi definido
     * pelo middleware (indicando autenticação bem-sucedida).
     *
     * @param ServerRequestInterface $request A requisição
     * @return int|null ID do usuário ou null se não autenticado
     * @see JwtAuthMiddleware
     */
    public static function getUserIdFromToken(ServerRequestInterface $request): ?int
    {
        // Se o middleware já validou, retorna o user_id dos atributos
        $userId = $request->getAttribute('user_id');
        if ($userId !== null) {
            return (int) $userId;
        }

        // Fallback: tenta ler do header Authorization (para compatibilidade)
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        // Retorna null se não houve autenticação prévia
        return null;
    }
}
