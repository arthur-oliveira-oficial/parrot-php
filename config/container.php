<?php

declare(strict_types=1);

/**
 * ===========================================
 * Configuração do Container (PHP-DI)
 * ===========================================
 *
 * Este arquivo define todas as dependências da aplicação
 * usando o container de injeção de dependência PHP-DI.
 *
 * O container gerencia:
 * - Criação de objetos (factories)
 * - Injeção de dependências
 * - Singletons quando necessário
 *
 * Configurações disponíveis no .env:
 * - DB_DRIVER, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
 * - JWT_SECRET, JWT_EXPIRY
 * - CORS_ALLOWED_ORIGINS
 * - RATE_LIMIT_MAX_REQUESTS, RATE_LIMIT_WINDOW_SECONDS
 *
 * @see https://php-di.org/ PHP-DI Documentation
 */

use App\Controllers\UserController;
use App\Controllers\AuthController;
use App\Cache\ApcuKeyValueStore;
use App\Cache\ArrayKeyValueStore;
use App\Cache\KeyValueStoreInterface;
use App\Cache\RedisKeyValueStore;
use App\Core\DatabaseCapsule;
use App\Core\JwtService;
use App\Middlewares\CsrfGuardMiddleware;
use App\Middlewares\JwtAuthMiddleware;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\SecurityHeadersMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;
use App\Models\TokenRevogado;
use App\Models\UserModel;
use App\Views\UserResource;
use Nyholm\Psr7\Factory\Psr17Factory;
use Predis\Client as PredisClient;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Helper para obter variáveis de ambiente
 *
 * Combina $_ENV, $_SERVER e getenv() para máxima compatibilidade.
 *
 * @param string $key Nome da variável
 * @param mixed $default Valor padrão
 * @return mixed Valor da variável ou padrão
 */
if (!function_exists('env_config')) {
    function env_config(string $key, mixed $default = null): mixed
    {
        // Prioriza $_ENV (em memória) para melhor performance
        // depois $_SERVER (também em memória)
        // e por último getenv() (mais lento, chamda de sistema)
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        $valor = getenv($key);
        return $valor !== false ? $valor : $default;
    }
}

if (!function_exists('origin_from_url')) {
    function origin_from_url(string $url): ?string
    {
        $partes = parse_url($url);

        if (!is_array($partes) || !isset($partes['scheme'], $partes['host'])) {
            return null;
        }

        $origem = $partes['scheme'] . '://' . $partes['host'];

        if (isset($partes['port'])) {
            $origem .= ':' . $partes['port'];
        }

        return $origem;
    }
}

if (!function_exists('normalizar_origens_permitidas')) {
    function normalizar_origens_permitidas(array $origens): array
    {
        $origensNormalizadas = [];

        foreach ($origens as $origem) {
            if (!is_string($origem)) {
                continue;
            }

            $origem = trim($origem);

            if ($origem === '') {
                continue;
            }

            $origensNormalizadas[$origem] = true;
        }

        return array_keys($origensNormalizadas);
    }
}

$appUrl = (string) env_config('APP_URL', 'http://localhost:8000');
$appOrigin = origin_from_url($appUrl);
$corsAllowedOrigins = array_filter(array_map(
    'trim',
    explode(',', (string) env_config('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'))
));

if ($appOrigin !== null) {
    $corsAllowedOrigins[] = $appOrigin;
}

$corsAllowedOrigins = normalizar_origens_permitidas($corsAllowedOrigins);

/**
 * Definições do Container
 *
 * Array de dependências retornado para o PHP-DI.
 */
return [
    ResponseFactoryInterface::class => new Psr17Factory(),

    'config' => [
        'db' => [
            'driver' => env_config('DB_DRIVER', 'mysql'),
            'host' => env_config('DB_HOST', 'localhost'),
            'port' => env_config('DB_PORT', '3306'),
            'name' => env_config('DB_DATABASE', env_config('DB_NAME', 'parrot_db')),
            'user' => env_config('DB_USER', 'root'),
            'password' => env_config('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],

        'jwt' => [
            'secret' => env_config('JWT_SECRET', ''),
            'expiry' => (int) env_config('JWT_EXPIRY', 3600),
            'issuer' => env_config('JWT_ISSUER', $appUrl),
            'audience' => env_config('JWT_AUDIENCE', 'parrot-api'),
        ],
        'app' => [
            'url' => $appUrl,
            'origin' => $appOrigin,
            'env' => (string) env_config('APP_ENV', 'development'),
        ],
        'cors' => [
            'allowed_origins' => $corsAllowedOrigins,
        ],
        'rate_limit' => [
            'max_requests' => (int) (env_config('RATE_LIMIT_MAX_REQUESTS', 60)),
            'window_seconds' => (int) (env_config('RATE_LIMIT_WINDOW_SECONDS', 60)),
        ],
        // Rate limit específico para login (prevenção de brute force)
        'rate_limit_login' => [
            'max_requests' => (int) (env_config('RATE_LIMIT_LOGIN_MAX_REQUESTS', 5)),
            'window_seconds' => (int) (env_config('RATE_LIMIT_LOGIN_WINDOW_SECONDS', 900)),
        ],
        'cache' => [
            'store' => env_config('CACHE_STORE', 'auto'),
            'prefix' => env_config('CACHE_PREFIX', 'parrot:'),
        ],
        'redis' => [
            'scheme' => env_config('REDIS_SCHEME', 'tcp'),
            'host' => env_config('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env_config('REDIS_PORT', 6379),
            'database' => (int) env_config('REDIS_DATABASE', 0),
            'password' => env_config('REDIS_PASSWORD', null),
            'timeout' => (float) env_config('REDIS_TIMEOUT', 1.5),
        ],
        'trusted_proxy_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env_config('TRUSTED_PROXY_IPS', ''))
        ))),
    ],

    KeyValueStoreInterface::class => function ($container) {
        $config = $container->get('config');
        $store = $config['cache']['store'];
        $ambiente = $config['app']['env'];

        if ($ambiente === 'production' && in_array($store, ['array', 'memory', 'apcu'], true)) {
            throw new \RuntimeException(
                'CACHE_STORE=' . $store . ' não é permitido em produção. Configure Redis para rate limit e blacklist distribuídos.'
            );
        }

        if ($store === 'array' || $store === 'memory' || env_config('APP_ENV') === 'testing') {
            return new ArrayKeyValueStore();
        }

        if ($store === 'redis' || ($store === 'auto' && class_exists(PredisClient::class) && env_config('REDIS_HOST', '') !== '')) {
            $redisConfig = $config['redis'];
            $clienteRedis = new PredisClient([
                'scheme' => $redisConfig['scheme'],
                'host' => $redisConfig['host'],
                'port' => $redisConfig['port'],
                'database' => $redisConfig['database'],
                'password' => $redisConfig['password'],
                'timeout' => $redisConfig['timeout'],
            ]);

            try {
                $clienteRedis->connect();

                return new RedisKeyValueStore(
                    $clienteRedis,
                    $config['cache']['prefix']
                );
            } catch (\Throwable) {
                if ($store === 'redis') {
                    throw new \RuntimeException('Não foi possível conectar ao Redis configurado para cache.');
                }

                if ($ambiente === 'production') {
                    throw new \RuntimeException(
                        'Produção exige Redis disponível para cache, rate limit e blacklist de JWT.'
                    );
                }
            }
        }

        if ($store === 'apcu' || ($store === 'auto' && function_exists('apcu_enabled') && apcu_enabled())) {
            if ($ambiente === 'production') {
                throw new \RuntimeException(
                    'Produção exige cache distribuído. APCu não é suficiente para rate limit e blacklist entre workers.'
                );
            }

            return new ApcuKeyValueStore();
        }

        if ($ambiente === 'production') {
            throw new \RuntimeException(
                'Produção exige Redis configurado explicitamente ou disponível no modo auto.'
            );
        }

        return new ArrayKeyValueStore();
    },

    DatabaseCapsule::class => function ($container) {
        $dbConfig = $container->get('config')['db'];
        return new DatabaseCapsule($dbConfig);
    },

    JwtService::class => function ($container) {
        $jwtConfig = $container->get('config')['jwt'];

        return new JwtService(
            (string) $jwtConfig['secret'],
            (int) $jwtConfig['expiry'],
            (string) $jwtConfig['issuer'],
            (string) $jwtConfig['audience']
        );
    },

    CorsMiddleware::class => function ($container) {
        $corsConfig = $container->get('config')['cors'];
        return new CorsMiddleware($corsConfig['allowed_origins']);
    },

    CsrfGuardMiddleware::class => function ($container) {
        $config = $container->get('config');

        return new CsrfGuardMiddleware(
            $config['cors']['allowed_origins'],
            $config['app']['origin']
        );
    },

    RateLimitMiddleware::class => function ($container) {
        $rateLimitConfig = $container->get('config')['rate_limit'];
        return new RateLimitMiddleware(
            $container->get(KeyValueStoreInterface::class),
            $container->get(JwtService::class),
            $container->get('config')['trusted_proxy_ips'],
            $rateLimitConfig['max_requests'],
            $rateLimitConfig['window_seconds']
        );
    },

    // Rate limit específico para rotas de autenticação (5 tentativas a cada 15 minutos)
    'rate_limit_login' => function ($container) {
        $rateLimitConfig = $container->get('config')['rate_limit_login'];
        return new RateLimitMiddleware(
            $container->get(KeyValueStoreInterface::class),
            $container->get(JwtService::class),
            $container->get('config')['trusted_proxy_ips'],
            $rateLimitConfig['max_requests'],
            $rateLimitConfig['window_seconds']
        );
    },

    SecurityHeadersMiddleware::class => function ($container) {
        return new SecurityHeadersMiddleware(
            $container->get('config')['trusted_proxy_ips']
        );
    },

    ErrorHandlerMiddleware::class => function ($container) {
        $env = (string) env_config('APP_ENV', 'development');
        $debug = env_config('APP_DEBUG');

        $displayErrors = ($env === 'development') && ($debug !== 'false');

        return new ErrorHandlerMiddleware(
            $container->get(ResponseFactoryInterface::class),
            $displayErrors
        );
    },

    UserModel::class => function ($container) {
        $container->get(DatabaseCapsule::class);
        return new UserModel();
    },

    TokenRevogado::class => function ($container) {
        TokenRevogado::definirCacheStore($container->get(KeyValueStoreInterface::class));
        return new TokenRevogado();
    },

    UserResource::class => function () {
        return new UserResource();
    },

    UserController::class => function ($container) {
        return new UserController(
            $container->get(UserModel::class),
            $container->get(UserResource::class)
        );
    },

    AuthController::class => function ($container) {
        $container->get(TokenRevogado::class);

        return new AuthController(
            $container->get(UserModel::class),
            $container->get(UserResource::class),
            $container->get(JwtService::class),
            $container->get('config')['trusted_proxy_ips']
        );
    },

    JwtAuthMiddleware::class => function ($container) {
        $container->get(TokenRevogado::class);
        return new JwtAuthMiddleware($container->get(JwtService::class));
    },
];
