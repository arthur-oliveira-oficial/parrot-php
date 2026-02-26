<?php

namespace App\Middlewares;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    private static array $storage = [];

    private readonly int $maxRequests;

    private readonly int $windowSeconds;

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
