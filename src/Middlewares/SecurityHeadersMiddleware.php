<?php

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware para adicionar headers de segurança HTTP.
 * Implementa PSR-15 MiddlewareInterface.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Processa a requisição adicionando headers de segurança.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);

        // Headers de segurança
        return $response
            // Previne que navegadores interpretem o conteúdo como outro tipo
            ->withHeader('X-Content-Type-Options', 'nosniff')

            // Previne clickjacking
            ->withHeader('X-Frame-Options', 'DENY')

            // Protege contra XSS
            ->withHeader('X-XSS-Protection', '1; mode=block')

            // Política de Same-Origin
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')

            // Content Security Policy básica
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'"
            )

            // HSTS - Force HTTPS (apenas em produção)
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')

            // Remove informações do servidor
            ->withHeader('X-Powered-By', '');
    }
}
