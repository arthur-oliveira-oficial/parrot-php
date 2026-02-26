<?php

/**
 * Parrot PHP Framework - Rate Limit Middleware
 *
 * Middleware de limitação de requisições (Rate Limiting).
 *
 * Protege a API contra abusos e ataques de força bruta
 * limitando o número de requisições por IP em um período.
 *
 * Implementação:
 * - Armazenamento em memória (array estático)
 * - Identificação por IP do cliente
 * - Retorna HTTP 429 quando excedido
 * - Headers informativos: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset
 *
 * @see https://developer.mozilla.org/pt-BR/docs/Web/HTTP/Status/429 HTTP 429 Too Many Requests
 */

namespace App\Middlewares;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de Rate Limiting
 *
 * Limita requisições por IP em um janela de tempo.
 *
 * Configuração padrão: 60 requisições por minuto
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var array Armazenamento em memória (em produção, use Redis ou banco) */
    private static array $storage = [];

    /** @var int Máximo de requisições permitidas na janela */
    private readonly int $maxRequests;

    /** @var int Janela de tempo em segundos */
    private readonly int $windowSeconds;

    /**
     * Construtor
     *
     * @param int $maxRequests Máximo de requisições (padrão: 60)
     * @param int $windowSeconds Janela de tempo em segundos (padrão: 60)
     */
    public function __construct(
        int $maxRequests = 60,
        int $windowSeconds = 60
    ) {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $identifier = $this->getIdentifier($request);

        if ($this->isRateLimited($identifier)) {
            $resetTime = self::$storage[$identifier]['reset'] ?? time();
            $retryAfter = max(0, $resetTime - time());

            return new Response(429, [
                'Content-Type' => 'application/json',
                'Retry-After' => (string) $retryAfter,
            ], json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Limite de requisições excedido. Tente novamente mais tarde.',
                'retry_after' => $retryAfter,
            ], JSON_UNESCAPED_UNICODE));
        }

        $this->incrementCounter($identifier);

        $response = $handler->handle($request);

        $remaining = $this->maxRequests - (self::$storage[$identifier]['count'] ?? 0);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $remaining))
            ->withHeader('X-RateLimit-Reset', (string) (self::$storage[$identifier]['reset'] ?? time() + $this->windowSeconds));
    }

    private function getIdentifier(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
        $realIp = $request->getHeaderLine('X-Real-IP');

        if (!empty($forwardedFor)) {
            $ips = explode(',', $forwardedFor);
            return trim($ips[0]);
        }

        if (!empty($realIp)) {
            return $realIp;
        }

        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    private function isRateLimited(string $identifier): bool
    {
        if (!isset(self::$storage[$identifier])) {
            return false;
        }

        $now = time();
        $record = self::$storage[$identifier];

        if ($now >= $record['reset']) {
            unset(self::$storage[$identifier]);
            return false;
        }

        return $record['count'] >= $this->maxRequests;
    }

    private function incrementCounter(string $identifier): void
    {
        $now = time();

        if (!isset(self::$storage[$identifier])) {
            self::$storage[$identifier] = [
                'count' => 1,
                'reset' => $now + $this->windowSeconds,
            ];
            return;
        }

        if ($now >= self::$storage[$identifier]['reset']) {
            self::$storage[$identifier] = [
                'count' => 1,
                'reset' => $now + $this->windowSeconds,
            ];
            return;
        }

        self::$storage[$identifier]['count']++;
    }

    public static function clearStorage(): void
    {
        self::$storage = [];
    }
}
