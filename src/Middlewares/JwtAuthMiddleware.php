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

        $payload = $this->validarToken($token);

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
        // Lê exclusivamente do cookie HttpOnly (protegido contra XSS)
        $cookies = $request->getCookieParams();
        return $cookies['token'] ?? null;
    }

    /**
     * Valida o token JWT
     *
     * Validações realizadas:
     * 1. Estrutura (3 partes separadas por ponto)
     * 2. Assinatura HMAC-SHA256
     * 3. Expiração (exp claim)
     *
     * @param string $token Token JWT
     * @return array|null Payload do token ou null se inválido
     */
    private function validarToken(string $token): ?array
    {
        // JWT tem 3 partes: header.payload.signature
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        // Obtém segredo do .env
        $secret = $_ENV['JWT_SECRET'] ?? null;
        if (empty($secret)) {
            throw new \RuntimeException('JWT_SECRET não configurado. Defina a variável JWT_SECRET no arquivo .env');
        }

        // Calcula assinatura esperada
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true)
        );

        // Compara assinaturas (timing attack safe)
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decodifica payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return null;
        }

        // Verifica expiração
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
