<?php

namespace App\Core;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;

/**
 * Classe principal que orquestra o ciclo de vida da aplicação.
 * Implementa PSR-15 RequestHandlerInterface.
 */
class Application implements RequestHandlerInterface
{
    private ContainerInterface $container;

    private FastRouteRouter $router;

    /**
     * Fila de middlewares globais.
     *
     * @var array<int, MiddlewareInterface>
     */
    private array $middlewares = [];

    public function __construct(
        private readonly string $basePath,
        private readonly string $configPath
    ) {
        $env = getenv('APP_ENV') ?: 'development';
        $cachePath = rtrim($basePath, '/') . '/cache';

        $this->router = new FastRouteRouter($env, $cachePath);
    }

    /**
     * Define o container de dependências.
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;
        $this->router->setContainer($container);

        // Configura a fábrica de respostas PSR-17 se disponível
        if ($container->has(\Psr\Http\Message\ResponseFactoryInterface::class)) {
            $this->router->setResponseFactory(
                $container->get(\Psr\Http\Message\ResponseFactoryInterface::class)
            );
        }

        return $this;
    }

    /**
     * Obtém o container de dependências.
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Carrega as configurações do container.
     */
    public function loadContainerConfig(): self
    {
        $configFile = $this->configPath . '/container.php';

        if (file_exists($configFile)) {
            $definitions = require $configFile;

            // O PHP-DI pode aceitar um array de definições diretamente
            if (is_array($definitions)) {
                foreach ($definitions as $id => $definition) {
                    $this->container->set($id, $definition);
                }
            }
        }

        return $this;
    }

    /**
     * Carrega as rotas de um arquivo de configuração.
     */
    public function loadRoutes(): self
    {
        $routesFile = $this->configPath . '/routes.php';

        if (file_exists($routesFile)) {
            $this->router->loadRoutes($routesFile);
        }

        return $this;
    }

    /**
     * Carrega os middlewares globais de um arquivo de configuração.
     */
    public function loadMiddlewares(): self
    {
        $middlewaresFile = $this->configPath . '/middlewares.php';

        if (file_exists($middlewaresFile)) {
            $middlewares = require $middlewaresFile;

            foreach ($middlewares as $middleware) {
                $this->addMiddleware($middleware);
            }
        }

        return $this;
    }

    /**
     * Adiciona um middleware global.
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): self
    {
        // Se for uma string (nome da classe), tenta resolver do container
        if (is_string($middleware) && isset($this->container)) {
            $middleware = $this->container->get($middleware);
        }

        if ($middleware instanceof MiddlewareInterface) {
            $this->middlewares[] = $middleware;
        }

        return $this;
    }

    /**
     * Processa a requisição HTTP.
     * Implementa PSR-15 RequestHandlerInterface.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Se há middlewares, cria uma cadeia de processamento
        if (!empty($this->middlewares)) {
            return $this->processMiddlewareQueue($request);
        }

        // Sem middlewares, processa direto pelo router
        return $this->router->handle($request);
    }

    /**
     * Processa a fila de middlewares.
     */
    private function processMiddlewareQueue(ServerRequestInterface $request): ResponseInterface
    {
        $middlewareStack = new MiddlewareQueue($this->middlewares);

        // Adiciona o router como handler final
        $middlewareStack->setHandler($this->router);

        return $middlewareStack->handle($request);
    }

    /**
     * Inicia a aplicação - método de conveniência.
     */
    public function run(): void
    {
        // Criar ServerRequest a partir das variáveis globais
        $serverRequest = $this->createRequestFromGlobals();

        // Configurar ambiente de erro
        $this->configureErrorHandling();

        // Processar requisição (middlewares incluindo ErrorHandlerMiddleware tratam exceções)
        $response = $this->handle($serverRequest);

        // Enviar resposta
        $this->sendResponse($response);
    }

    /**
     * Cria uma requisição PSR-7 a partir das variáveis globais do PHP.
     */
    private function createRequestFromGlobals(): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();

        $serverRequestCreator = new ServerRequestCreator(
            $psr17Factory, // ServerRequestFactory
            $psr17Factory, // StreamFactory
            $psr17Factory, // UploadedFileFactory
            $psr17Factory  // ResponseFactory
        );

        return $serverRequestCreator->fromGlobals();
    }

    /**
     * Configura o tratamento de erros baseado no ambiente.
     */
    private function configureErrorHandling(): void
    {
        $env = getenv('APP_ENV') ?: 'development';
        $debug = getenv('APP_DEBUG');

        if ($env === 'production' || $debug === 'false') {
            error_reporting(0);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }
    }

    /**
     * Envia a resposta HTTP para o cliente.
     */
    private function sendResponse(ResponseInterface $response): void
    {
        // Status code
        http_response_code($response->getStatusCode());

        // Headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        // Body
        echo $response->getBody();
    }
}
