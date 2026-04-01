<?php

declare(strict_types=1);

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

use App\Cache\ArrayKeyValueStore;
use App\Cache\KeyValueStoreInterface;
use App\Core\JwtService;
use App\Core\Response as AppResponse;
use App\Models\TokenRevogado;
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
        private readonly KeyValueStoreInterface $store,
        private readonly JwtService $jwtService,
        private readonly array $ipsProxyConfiavel = [],
        int $maxRequests = 60,
        int $windowSeconds = 60
    ) {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
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
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        $identifier = $this->getIdentifier($request);
        $counterKey = $this->getStorageKey($identifier);
        $requestCount = $this->store->increment($counterKey, $this->windowSeconds);
        $ttl = $this->store->ttl($counterKey) ?? $this->windowSeconds;
        $remaining = max(0, $this->maxRequests - $requestCount);

        if ($requestCount > $this->maxRequests) {
            return AppResponse::tooManyRequests('Limite de requisições excedido. Tente novamente mais tarde.', $ttl)
                ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', (string) (time() + $ttl));
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) (time() + $ttl));
    }

    /**
     * Obtém o identificador único para rate limiting
     *
     * 1. Tenta obter o ID do usuário via cookie JWT. Se o usuário estiver autenticado
     *    com um token JWT VÁLIDO (assinatura verificada), o rate limit é aplicado por usuário,
     *    evitando que dispositivos em uma mesma rede corporativa bloqueiem uns aos outros.
     * 2. Fallback: Por defeito, usa apenas REMOTE_ADDR.
     *    X-Forwarded-For e X-Real-IP apenas sao confiados se:
     *    a. TRUSTED_PROXY_IPS estiver configurado
     *    b. O REMOTE_ADDR estiver na lista de proxies confiaveis
     *
     * Isso previne ataques de IP spoofing, falso-positivos em redes NAT e Rate Limit Bypass via forged JWT.
     */
    private function getIdentifier(ServerRequestInterface $request): string
    {
        $cookies = $request->getCookieParams();
        $token = $cookies['token'] ?? null;

        if (is_string($token)) {
            $payload = $this->jwtService->validarToken($token);

            if (
                is_array($payload) &&
                isset($payload['sub']) &&
                (!isset($payload['jti']) || !TokenRevogado::estaRevogado((string) $payload['jti']))
            ) {
                return 'user:' . (string) $payload['sub'];
            }
        }

        $serverParams = $request->getServerParams();
        $remoteAddr = is_string($serverParams['REMOTE_ADDR'] ?? null) ? $serverParams['REMOTE_ADDR'] : 'unknown';

        if ($this->ipEhConfiavel($remoteAddr)) {
            $cfIp = trim($request->getHeaderLine('CF-Connecting-IP'));
            if ($cfIp !== '' && filter_var($cfIp, FILTER_VALIDATE_IP)) {
                return $cfIp;
            }

            $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
            if ($forwardedFor !== '') {
                foreach (array_map('trim', explode(',', $forwardedFor)) as $ipInformado) {
                    if (filter_var($ipInformado, FILTER_VALIDATE_IP)) {
                        return $ipInformado;
                    }
                }
            }
        }

        return $remoteAddr;
    }

    private function ipEhConfiavel(string $ip): bool
    {
        return $ip !== '' && in_array($ip, $this->ipsProxyConfiavel, true);
    }

    public static function clearStorage(): void
    {
        (new ArrayKeyValueStore())->clear();
    }
}
