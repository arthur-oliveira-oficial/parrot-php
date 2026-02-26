<?php

/**
 * Front Controller - Ponto de entrada único da aplicação.
 *
 * Este é o único arquivo PHP exposto pelo servidor web.
 * Todas as requisições passam por aqui primeiro.
 */

declare(strict_types=1);

// Tratamento global de erros - captura exceções antes do middleware
set_exception_handler(function(\Throwable $e) {
    // ESSENCIAL PARA PRODUÇÃO: gravar o erro para os devs investigarem!
    error_log((string) $e);

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'status' => 500,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(1);
});

// Carrega o autoloader do Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Carrega variáveis de ambiente (.env)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Disponibiliza variáveis no $_ENV
$_ENV = array_merge($_ENV, $_SERVER);

// Determina o ambiente
$env = $_ENV['APP_ENV'] ?? 'development';

// Define caminhos base
$basePath = dirname(__DIR__);
$configPath = $basePath . '/config';
$cachePath = $basePath . '/cache';

// Importa classes necessárias
use App\Core\Application;
use DI\ContainerBuilder;

// Configura o container de dependências
$containerBuilder = new ContainerBuilder();

// Cache em produção para melhor performance
if ($env === 'production') {
    $containerBuilder->enableCompilation($cachePath);
    $containerBuilder->writeProxiesToFile(true, $cachePath . '/proxies');
}

// Carrega configurações do container
$definitions = require $configPath . '/container.php';
$container = $containerBuilder->addDefinitions($definitions)->build();

// Cria e configura a aplicação
$app = new Application($basePath, $configPath);
$app->setContainer($container);

// Carrega rotas e middlewares
$app->loadRoutes()
    ->loadMiddlewares();

// Executa a aplicação
$app->run();
