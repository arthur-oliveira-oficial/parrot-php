<?php

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
use App\Core\DatabaseCapsule;
use App\Middlewares\JwtAuthMiddleware;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\SecurityHeadersMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;
use App\Models\UserModel;
use App\Views\UserResource;
use Nyholm\Psr7\Factory\Psr17Factory;
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
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
}

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
            'name' => env_config('DB_NAME', 'parrot_db'),
            'user' => env_config('DB_USER', 'root'),
            'password' => env_config('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],

        'jwt' => [
            'secret' => env_config('JWT_SECRET', 'default-secret-change-me'),
            'expiry' => env_config('JWT_EXPIRY', 3600),
        ],
        'cors' => [
            'allowed_origins' => explode(',', env_config('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
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
    ],

    DatabaseCapsule::class => function ($container) {
        $dbConfig = $container->get('config')['db'];
        return new DatabaseCapsule($dbConfig);
    },

    CorsMiddleware::class => function ($container) {
        $corsConfig = $container->get('config')['cors'];
        return new CorsMiddleware($corsConfig['allowed_origins']);
    },

    RateLimitMiddleware::class => function ($container) {
        $rateLimitConfig = $container->get('config')['rate_limit'];
        return new RateLimitMiddleware(
            $rateLimitConfig['max_requests'],
            $rateLimitConfig['window_seconds']
        );
    },

    // Rate limit específico para rotas de autenticação (5 tentativas a cada 15 minutos)
    'rate_limit_login' => function ($container) {
        $rateLimitConfig = $container->get('config')['rate_limit_login'];
        return new RateLimitMiddleware(
            $rateLimitConfig['max_requests'],
            $rateLimitConfig['window_seconds']
        );
    },

    SecurityHeadersMiddleware::class => function () {
        return new SecurityHeadersMiddleware();
    },

    ErrorHandlerMiddleware::class => function ($container) {
        $env = getenv('APP_ENV') ?: 'development';
        $debug = getenv('APP_DEBUG');

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
        return new AuthController(
            $container->get(UserModel::class),
            $container->get(UserResource::class)
        );
    },

    JwtAuthMiddleware::class => function () {
        return new JwtAuthMiddleware();
    },
];
