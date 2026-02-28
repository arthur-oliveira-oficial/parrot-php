<?php

/**
 * Parrot PHP Framework - Security Headers Middleware
 *
 * Middleware que adiciona headers de segurança HTTP à resposta.
 *
 * Headers adicionados:
 * - X-Content-Type-Options: Impede MIME-type sniffing
 * - X-Frame-Options: Protege contra clickjacking (iframes maliciosos)
 * - Referrer-Policy: Controla informações do referenciador
 * - Content-Security-Policy (CSP): Previne XSS e ataques de inclusão
 * - Strict-Transport-Security (HSTS): Força HTTPS
 *
 * Nota: X-XSS-Protection foi removido pois é um header legado
 * depreciado que pode introduzir vulnerabilidades. A proteção
 * contra XSS é garantida pela CSP.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers Security Headers
 * @see https://owasp.org/www-project-secure-headers/ OWASP Secure Headers
 */

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de Headers de Segurança
 *
 * Adiciona headers de segurança para proteger a aplicação
 * contra ataques comuns como XSS, clickjacking e MIME sniffing.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Processa a requisição adicionando headers de segurança
     *
     * O middleware executa o handler primeiro (para ter a resposta)
     * e então adiciona os headers de segurança à resposta.
     *
     * @param ServerRequestInterface $request Requisição HTTP
     * @param RequestHandlerInterface $handler Próximo handler na cadeia
     * @return ResponseInterface Resposta com headers de segurança
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'"
            )
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->withHeader('X-Powered-By', '');
    }
}
