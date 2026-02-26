<?php

/**
 * Parrot PHP Framework - CORS Middleware
 *
 * Middleware para suporte a Cross-Origin Resource Sharing (CORS).
 *
 * Permite que aplicações frontend em diferentes domínios
 * façam requisições para esta API.
 *
 * Headers adicionados:
 * - Access-Control-Allow-Origin: Origem permitida
 * - Access-Control-Allow-Methods: Métodos HTTP permitidos
 * - Access-Control-Allow-Headers: Headers permitidos
 * - Access-Control-Max-Age: Tempo de cache do preflight
 *
 * Preflight (OPTIONS):
 * - Browsers enviam requisição OPTIONS antes da requisição real
 * - Este middleware responde automaticamente
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS CORS Explained
 */

namespace App\Middlewares;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de CORS
 *
 * Configuração padrão permite:
 * - Origens: http://localhost:3000 (comum para React/Vue dev servers)
 * - Métodos: GET, POST, PUT, PATCH, DELETE, OPTIONS
 * - Headers: Content-Type, Authorization, X-Requested-With
 */
class CorsMiddleware implements MiddlewareInterface
{
    /** @var array Origens permitidas (domínios que podem acessar a API) */
    private readonly array $allowedOrigins;

    /** @var array Métodos HTTP permitidos em requisições cross-origin */
    private readonly array $allowedMethods;

    /** @var array Headers que o cliente pode enviar */
    private readonly array $allowedHeaders;

    /**
     * Construtor
     *
     * @param array $allowedOrigins Domínios permitidos
     * @param array $allowedMethods Métodos HTTP permitidos
     * @param array $allowedHeaders Headers permitidos
     */
    public function __construct(
        array $allowedOrigins = ['http://localhost:3000'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With']
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $origin = $request->getHeaderLine('Origin');

        if (!$this->isOriginAllowed($origin)) {
            $env = getenv('APP_ENV') ?: 'development';

            if ($env === 'development') {
                return $handler->handle($request);
            }

            return new Response(403, [], json_encode(['error' => 'Origin not allowed']));
        }

        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($origin);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', '3600');
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return true;
        }

        $env = getenv('APP_ENV') ?: 'development';
        if ($env === 'development') {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }

    private function handlePreflightRequest(string $origin): ResponseInterface
    {
        return new Response(204, [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => implode(', ', $this->allowedMethods),
            'Access-Control-Allow-Headers' => implode(', ', $this->allowedHeaders),
            'Access-Control-Max-Age' => '3600',
            'Access-Control-Allow-Credentials' => 'true',
        ]);
    }
}
