<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de Autenticação JWT
 * Valida token JWT do cookie httpOnly e adiciona user_id ao request.
 */
class JwtAuthMiddleware implements MiddlewareInterface
{
    /**
     * Processa a requisição validando o token JWT.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Tenta obter token do cookie
        $token = $this->obterTokenDoCookie($request);

        if (!$token) {
            return Response::unauthorized('Token de autenticação não fornecido');
        }

        // Valida o token
        $payload = $this->validarToken($token);

        if (!$payload) {
            return Response::unauthorized('Token de autenticação inválido ou expirado');
        }

        // Adiciona user_id aos atributos da requisição
        $request = $request->withAttribute('user_id', (int) $payload['sub']);
        $request = $request->withAttribute('user_email', $payload['email'] ?? '');
        $request = $request->withAttribute('user_tipo', $payload['tipo'] ?? '');

        return $handler->handle($request);
    }

    /**
     * Obtém o token do cookie.
     */
    private function obterTokenDoCookie(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();

        return $cookies['token'] ?? null;
    }

    /**
     * Valida o token JWT e retorna o payload.
     */
    private function validarToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        // Valida assinatura
        $secret = $_ENV['JWT_SECRET'] ?? 'development-secret-change-in-production';
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true)
        );

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

    /**
     * Decodifica string base64 URL-safe.
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Codifica string para base64 URL-safe.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
