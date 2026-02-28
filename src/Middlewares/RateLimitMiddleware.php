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
 *
 * Segurança:
 * - Não confia em X-Forwarded-For/X-Real-IP por padrão (IP spoofing protection)
 * - Apenas usa esses headers se TRUSTED_PROXY_IPS estiver configurado
 * - Usa APCu para persistência (com fallback seguro para array estático)
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var array Armazenamento em memória (fallback se APCu não disponível) */
    private static array $storage = [];

    /** @var int Máximo de IPs simultâneos no storage (prevenção DoS) */
    private const MAX_STORAGE_ENTRIES = 10000;

    /** @var int Máximo de requisições permitidas na janela */
    private readonly int $maxRequests;

    /** @var int Janela de tempo em segundos */
    private readonly int $windowSeconds;

    /** @var bool Se APCu está disponível */
    private readonly bool $apcuAvailable;

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
        $this->apcuAvailable = function_exists('apcu_enabled') && apcu_enabled();
    }

    /**
     * Gera a chave de storage para APCu
     */
    private function getStorageKey(string $identifier): string
    {
        return 'ratelimit_' . $identifier;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $identifier = $this->getIdentifier($request);

        $rateLimitInfo = $this->getRateLimitInfo($identifier);

        if ($rateLimitInfo['limited']) {
            return new Response(429, [
                'Content-Type' => 'application/json',
                'Retry-After' => (string) $rateLimitInfo['retry_after'],
            ], json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Limite de requisições excedido. Tente novamente mais tarde.',
                'retry_after' => $rateLimitInfo['retry_after'],
            ], JSON_UNESCAPED_UNICODE));
        }

        $this->incrementCounter($identifier);

        $response = $handler->handle($request);

        $infoAfter = $this->getRateLimitInfo($identifier);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $infoAfter['remaining'])
            ->withHeader('X-RateLimit-Reset', (string) $infoAfter['reset']);
    }

    /**
     * Obtém informação de rate limit atual
     *
     * @return array{limited: bool, remaining: int, reset: int, retry_after: int}
     */
    private function getRateLimitInfo(string $identifier): array
    {
        if ($this->apcuAvailable) {
            return $this->getRateLimitInfoApcu($identifier);
        }
        return $this->getRateLimitInfoFallback($identifier);
    }

    /**
     * Obtém informação de rate limit via APCu
     */
    private function getRateLimitInfoApcu(string $identifier): array
    {
        $key = $this->getStorageKey($identifier);
        $record = apcu_fetch($key, $success);

        if (!$success) {
            return [
                'limited' => false,
                'remaining' => $this->maxRequests,
                'reset' => time() + $this->windowSeconds,
                'retry_after' => 0,
            ];
        }

        $now = time();

        if ($now >= $record['reset']) {
            return [
                'limited' => false,
                'remaining' => $this->maxRequests,
                'reset' => $now + $this->windowSeconds,
                'retry_after' => 0,
            ];
        }

        return [
            'limited' => $record['count'] >= $this->maxRequests,
            'remaining' => max(0, $this->maxRequests - $record['count']),
            'reset' => $record['reset'],
            'retry_after' => max(0, $record['reset'] - $now),
        ];
    }

    /**
     * Obtém informação de rate limit via array estático (fallback)
     */
    private function getRateLimitInfoFallback(string $identifier): array
    {
        if (!isset(self::$storage[$identifier])) {
            return [
                'limited' => false,
                'remaining' => $this->maxRequests,
                'reset' => time() + $this->windowSeconds,
                'retry_after' => 0,
            ];
        }

        $now = time();
        $record = self::$storage[$identifier];

        if ($now >= $record['reset']) {
            return [
                'limited' => false,
                'remaining' => $this->maxRequests,
                'reset' => $now + $this->windowSeconds,
                'retry_after' => 0,
            ];
        }

        return [
            'limited' => $record['count'] >= $this->maxRequests,
            'remaining' => max(0, $this->maxRequests - $record['count']),
            'reset' => $record['reset'],
            'retry_after' => max(0, $record['reset'] - $now),
        ];
    }

    /**
     * Obtém o identificador único para rate limiting
     *
     * Seguranca: Por defeito, usa apenas REMOTE_ADDR.
     * X-Forwarded-For e X-Real-IP apenas sao confiados se:
     * 1. TRUSTED_PROXY_IPS estiver configurado
     * 2. O REMOTE_ADDR estiver na lista de proxies confiaveis
     *
     * Isso previne ataques de IP spoofing.
     */
    private function getIdentifier(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? 'unknown';

        // Verificar se há proxy confiável configurado
        $trustedProxies = getenv('TRUSTED_PROXY_IPS') ?: '';

        if (!empty($trustedProxies)) {
            $trustedList = array_map('trim', explode(',', $trustedProxies));

            // Se o IP de origem é um proxy confiável, usar X-Forwarded-For
            if (in_array($remoteAddr, $trustedList, true)) {
                $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
                if (!empty($forwardedFor)) {
                    $ips = explode(',', $forwardedFor);
                    return trim($ips[0]);
                }
            }
        }

        // Por defeito, usar apenas REMOTE_ADDR (seguro contra spoofing)
        return $remoteAddr;
    }

    /**
     * Incrementa o contador de requisições
     */
    private function incrementCounter(string $identifier): void
    {
        if ($this->apcuAvailable) {
            $this->incrementCounterApcu($identifier);
        } else {
            $this->incrementCounterFallback($identifier);
        }
    }

    /**
     * Incrementa contador via APCu
     */
    private function incrementCounterApcu(string $identifier): void
    {
        $key = $this->getStorageKey($identifier);
        $now = time();

        $record = apcu_fetch($key, $success);

        if (!$success || $now >= $record['reset']) {
            apcu_store($key, [
                'count' => 1,
                'reset' => $now + $this->windowSeconds,
            ], $this->windowSeconds + 60);
            return;
        }

        apcu_inc($key, 1);
    }

    /**
     * Incrementa contador via array estático (fallback com protecao)
     */
    private function incrementCounterFallback(string $identifier): void
    {
        // Limpar entradas expiradas com probabilidade de 1%
        // Isso previne memory leak em ambientes persistentes
        if (count(self::$storage) > self::MAX_STORAGE_ENTRIES) {
            $this->cleanupExpiredFallback();
        }

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

    /**
     * Limpa entradas expiradas do storage fallback (prevenção de memory leak)
     */
    private function cleanupExpiredFallback(): void
    {
        $now = time();
        foreach (self::$storage as $key => $record) {
            if ($now >= $record['reset']) {
                unset(self::$storage[$key]);
            }
        }
    }

    public static function clearStorage(): void
    {
        self::$storage = [];
    }
}
