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

        // Se a origem nao for permitida, verificar o ambiente
        if (!$this->isOriginAllowed($origin)) {
            $env = getenv('APP_ENV');

            // Se APP_ENV nao esta definido ou e producao, bloquear
            if ($env === false || $env !== 'development') {
                return new Response(403, [], json_encode(['error' => 'Origin not allowed']));
            }

            // Em desenvolvimento, continuar sem headers CORS (nao bloquear)
            return $handler->handle($request);
        }

        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($origin);
        }

        $response = $handler->handle($request);

        // Apenas permitir credenciais se a origem estiver na whitelist
        $allowCredentials = $this->isOriginWhitelisted($origin) ? 'true' : 'false';

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', $allowCredentials)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', '3600');
    }

    /**
     * Verifica se a origem é permitida
     *
     * Seguranca:
     * - Se APP_ENV nao estiver definido, recusa por seguranca
     * - Em desenvolvimento, usa lista explícita (nao permite qualquer origem)
     * - Em producao, usa whitelist estrita
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return true;
        }

        // Verificacao explícita - nao usar fallback automático
        $env = getenv('APP_ENV');

        if ($env === false) {
            // Variavel nao definida - recusar por seguranca
            return false;
        }

        if ($env === 'development') {
            // Em desenvolvimento, usar lista explícita (nao qualquer origem)
            return !empty($this->allowedOrigins) &&
                   in_array($origin, $this->allowedOrigins, true);
        }

        // Producao: whitelist estrita
        return in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * Verifica se a origem está na whitelist de origens permitidas
     * (usado para decidir se credenciais podem ser enviadas)
     */
    private function isOriginWhitelisted(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins, true);
    }

    private function handlePreflightRequest(string $origin): ResponseInterface
    {
        // Apenas permitir credenciais se a origem estiver na whitelist
        $allowCredentials = $this->isOriginWhitelisted($origin) ? 'true' : 'false';

        return new Response(204, [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => implode(', ', $this->allowedMethods),
            'Access-Control-Allow-Headers' => implode(', ', $this->allowedHeaders),
            'Access-Control-Max-Age' => '3600',
            'Access-Control-Allow-Credentials' => $allowCredentials,
        ]);
    }
}
