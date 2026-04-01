<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - JWT Authentication Middleware
 *
 * Middleware de autenticação usando JSON Web Token (JWT).
 *
 * Este middleware:
 * 1. Obtém o token do cookie 'token'
 * 2. Valida a assinatura do JWT
 * 3. Verifica se o token não expirou
 * 4. Adiciona dados do usuário na requisição (user_id, user_email, user_tipo)
 *
 * JWT (JSON Web Token):
 * - Padrão RFC 7519 para criar tokens de acesso
 * - Estrutura: header.payload.signature
 * - Stateless: não requer armazenamento no servidor
 *
 * @see https://jwt.io/ JWT Explained
 * @see https://developer.mozilla.org/pt-BR/docs/Web/HTTP/Authentication HTTP Authentication
 */

namespace App\Middlewares;

use App\Core\JwtService;
use App\Core\Response;
use App\Models\TokenRevogado;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de Autenticação JWT
 *
 * Protege rotas verificando token JWT válido.
 * O token é enviado via cookie HttpOnly (definido no login).
 */
class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwtService
    ) {
    }

    /**
     * Processa a requisição validando o token JWT
     *
     * Fluxo:
     * 1. Obtém token do cookie
     * 2. Se não existe: retorna 401
     * 3. Valida token (assinatura + expiração)
     * 4. Se inválido: retorna 401
     * 5. Adiciona dados do usuário na requisição
     * 6. Passa para o próximo handler
     *
     * @param ServerRequestInterface $request Requisição HTTP
     * @param RequestHandlerInterface $handler Próximo handler
     * @return ResponseInterface Resposta de erro ou sucesso
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->obterToken($request);

        if (!$token) {
            return Response::unauthorized('Token de autenticação não fornecido');
        }

        $payload = $this->jwtService->validarToken($token);

        if (!$payload) {
            return Response::unauthorized('Token de autenticação inválido ou expirado');
        }

        // Verifica se o token foi revogado (blacklist)
        if (isset($payload['jti']) && TokenRevogado::estaRevogado($payload['jti'])) {
            return Response::unauthorized('Token foi revogado');
        }

        $request = $request->withAttribute('user_id', (int) $payload['sub']);
        $request = $request->withAttribute('user_email', $payload['email'] ?? '');
        $request = $request->withAttribute('user_tipo', $payload['tipo'] ?? '');
        $request = $request->withAttribute('jwt_payload', $payload);

        return $handler->handle($request);
    }

    /**
     * Obtém o token JWT da requisição
     *
     * Lê exclusivamente do cookie HttpOnly (protegido contra XSS).
     * O token nunca deve ser aceito via header para evitar
     * ataques XSS que podem ler/injetar tokens.
     *
     * @param ServerRequestInterface $request Requisição HTTP
     * @return string|null Token encontrado ou null
     */
    private function obterToken(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        return is_string($cookies['token'] ?? null) ? $cookies['token'] : null;
    }
}
