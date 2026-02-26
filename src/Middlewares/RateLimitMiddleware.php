<?php

namespace App\Middlewares;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware para limitação de taxa de requisições (Rate Limiting).
 * Protege contra ataques de força bruta e DDoS básico.
 * Implementa PSR-15 MiddlewareInterface.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Armazenamento em memória (em produção, use Redis ou Memcached).
     *
     * @var array<string, array{count: int, reset: int}>
     */
    private static array $storage = [];

    /**
     * Número máximo de requisições permitidas.
     */
    private readonly int $maxRequests;

    /**
     * Janela de tempo em segundos.
     */
    private readonly int $windowSeconds;

    public function __construct(
        int $maxRequests = 60,
        int $windowSeconds = 60
    ) {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Processa a requisição verificando o limite de taxa.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $identifier = $this->getIdentifier($request);

        // Verifica se excedeu o limite
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

        // Incrementa contador
        $this->incrementCounter($identifier);

        // Processa a requisição
        $response = $handler->handle($request);

        // Adiciona headers de rate limit
        $remaining = $this->maxRequests - (self::$storage[$identifier]['count'] ?? 0);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $remaining))
            ->withHeader('X-RateLimit-Reset', (string) (self::$storage[$identifier]['reset'] ?? time() + $this->windowSeconds));
    }

    /**
     * Obtém o identificador único para rate limiting.
     * Usa IP do cliente por padrão.
     */
    private function getIdentifier(ServerRequestInterface $request): string
    {
        // Tenta obter IP real considerando proxies
        $serverParams = $request->getServerParams();

        // Verifica headers de proxy (X-Forwarded-For, X-Real-IP)
        $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
        $realIp = $request->getHeaderLine('X-Real-IP');

        if (!empty($forwardedFor)) {
            // Pega o primeiro IP da lista (cliente original)
            $ips = explode(',', $forwardedFor);
            return trim($ips[0]);
        }

        if (!empty($realIp)) {
            return $realIp;
        }

        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Verifica se o identificador excedeu o limite.
     */
    private function isRateLimited(string $identifier): bool
    {
        if (!isset(self::$storage[$identifier])) {
            return false;
        }

        $now = time();
        $record = self::$storage[$identifier];

        // Janela expirou, reseta o contador
        if ($now >= $record['reset']) {
            unset(self::$storage[$identifier]);
            return false;
        }

        return $record['count'] >= $this->maxRequests;
    }

    /**
     * Incrementa o contador de requisições.
     */
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

        // Reseta se a janela expirou
        if ($now >= self::$storage[$identifier]['reset']) {
            self::$storage[$identifier] = [
                'count' => 1,
                'reset' => $now + $this->windowSeconds,
            ];
            return;
        }

        self::$storage[$identifier]['count']++;
    }

    /**
     * Limpa o storage (útil para testes).
     */
    public static function clearStorage(): void
    {
        self::$storage = [];
    }
}
