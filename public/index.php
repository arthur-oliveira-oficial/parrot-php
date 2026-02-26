<?php

declare(strict_types=1);

set_exception_handler(function(\Throwable $e) {
    error_log((string) $e);

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'status' => 500,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(1);
});

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$_ENV = array_merge($_ENV, $_SERVER);

$env = $_ENV['APP_ENV'] ?? 'development';

$basePath = dirname(__DIR__);
$configPath = $basePath . '/config';
$cachePath = $basePath . '/cache';

use App\Core\Application;
use DI\ContainerBuilder;

$containerBuilder = new ContainerBuilder();

if ($env === 'production') {
    $containerBuilder->enableCompilation($cachePath);
    $containerBuilder->writeProxiesToFile(true, $cachePath . '/proxies');
}

$definitions = require $configPath . '/container.php';
$container = $containerBuilder->addDefinitions($definitions)->build();

$app = new Application($basePath, $configPath);
$app->setContainer($container);

$app->loadRoutes()
    ->loadMiddlewares();

$app->run();
