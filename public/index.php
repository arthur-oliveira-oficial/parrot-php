<?php

declare(strict_types=1);

/**
 * ===========================================
 * Parrot PHP Framework - Entry Point (Front Controller)
 * ===========================================
 *
 * Este é o ponto de entrada único da aplicação.
 * Todas as requisições HTTP passam por este arquivo.
 *
 * Fluxo de inicialização:
 * 1. Handler de exceção global (último recurso)
 * 2. Autoloader do Composer
 * 3. Variáveis de ambiente (.env)
 * 4. Container de dependências (PHP-DI)
 * 5. Aplicação (routes + middlewares)
 * 6. Execução da requisição
 *
 * @see https://en.wikipedia.org/wiki/Front_controller Front Controller Pattern
 */

/**
 * Handler de exceção global
 *
 * Captura qualquer exceção não tratada.
 * Importante: este é o último recurso - o ErrorHandlerMiddleware
 * deve tratar a maioria dos erros.
 */
set_exception_handler(function(\Throwable $e) {
    // Loga o erro para debug
    error_log((string) $e);

    // Retorna JSON genérico (não expõe detalhes em produção)
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'status' => 500,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(1);
});

// ===========================================
// 1. Autoloader do Composer
// ===========================================
// Carrega todas as classes automaticamente
require_once __DIR__ . '/../vendor/autoload.php';

// ===========================================
// 2. Variáveis de Ambiente (.env)
// ===========================================
// Determina ambiente primeiro (lê de variáveis do sistema)
$env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';

// Em desenvolvimento, carrega .env do arquivo
// Em produção, as variáveis devem ser injetadas pelo servidor/Docker
if ($env !== 'production') {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

// Mescla $_ENV com $_SERVER (necessário para algumas bibliotecas)
$_ENV = array_merge($_ENV, $_SERVER);

// ===========================================
// 3. Configuração de Caminhos
// ===========================================
$basePath = dirname(__DIR__);      // Pasta raiz do projeto
$configPath = $basePath . '/config'; // Pasta de configurações
$cachePath = $basePath . '/cache';  // Pasta de cache

// ===========================================
// 4. Container de Dependências (PHP-DI)
// ===========================================
use App\Core\Application;
use App\Core\DatabaseCapsule;
use DI\ContainerBuilder;

$containerBuilder = new ContainerBuilder();

// Em produção: compila o container para melhor performance
if ($env === 'production') {
    $containerBuilder->enableCompilation($cachePath);
    $containerBuilder->writeProxiesToFile(true, $cachePath . '/proxies');
}

// Carrega definições do container (services, factories, etc.)
$definitions = require $configPath . '/container.php';
$container = $containerBuilder->addDefinitions($definitions)->build();

// Inicializa o DatabaseCapsule (Eloquent) antes de qualquer requisição
$container->get(DatabaseCapsule::class);

// ===========================================
// 5. Inicialização da Aplicação
// ===========================================
$app = new Application($basePath, $configPath);
$app->setContainer($container);

// Carrega rotas e middlewares de configuração
$app->loadRoutes()
    ->loadMiddlewares();

// ===========================================
// 6. Execução
// ===========================================
// Inicia o processamento da requisição HTTP
$app->run();
