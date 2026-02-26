<?php

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
 * Configuração do Container de Injeção de Dependência (PSR-11).
 * Retorna um array de definições para ser usado com PHP-DI.
 */

// Função helper para obter variáveis de ambiente
function env_config(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

return [
    // PSR-17 Response Factory
    ResponseFactoryInterface::class => new Psr17Factory(),

    // Configurações do ambiente
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
    ],

    // Database Capsule - Eloquent ORM
    DatabaseCapsule::class => function ($container) {
        $dbConfig = $container->get('config')['db'];
        return new DatabaseCapsule($dbConfig);
    },

    // Middlewares
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

    // Models - UserModel com Eloquent
    UserModel::class => function ($container) {
        // DatabaseCapsule já inicializa o Eloquent globalmente
        $container->get(DatabaseCapsule::class);
        return new UserModel();
    },

    // Resources
    UserResource::class => function () {
        return new UserResource();
    },

    // Controllers
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

    // Middleware JWT
    JwtAuthMiddleware::class => function () {
        return new JwtAuthMiddleware();
    },
];
