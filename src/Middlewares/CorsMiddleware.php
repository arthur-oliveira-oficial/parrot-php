<?php

namespace App\Middlewares;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware para controle de CORS (Cross-Origin Resource Sharing).
 * Implementa PSR-15 MiddlewareInterface.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Origens permitidas.
     *
     * @var array<int, string>
     */
    private readonly array $allowedOrigins;

    /**
     * Métodos HTTP permitidos.
     *
     * @var array<int, string>
     */
    private readonly array $allowedMethods;

    /**
     * Headers permitidos.
     *
     * @var array<int, string>
     */
    private readonly array $allowedHeaders;

    public function __construct(
        array $allowedOrigins = ['http://localhost:3000'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With']
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
    }

    /**
     * Processa a requisição e aplica headers CORS.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $origin = $request->getHeaderLine('Origin');

        // Verifica se a origem é permitida
        if (!$this->isOriginAllowed($origin)) {
            // Em produção, pode retornar 403; em desenvolvimento, permite todas
            $env = getenv('APP_ENV') ?: 'development';

            if ($env === 'development') {
                return $handler->handle($request);
            }

            return new Response(403, [], json_encode(['error' => 'Origin not allowed']));
        }

        // Preflight request (OPTIONS)
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($origin);
        }

        // Processa a requisição normalmente
        $response = $handler->handle($request);

        // Adiciona headers CORS à resposta
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', '3600');
    }

    /**
     * Verifica se a origem é permitida.
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return true; // Requisiçãosame-origin
        }

        // Permite qualquer origem em desenvolvimento
        $env = getenv('APP_ENV') ?: 'development';
        if ($env === 'development') {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * Responde a uma requisição preflight (OPTIONS).
     */
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
