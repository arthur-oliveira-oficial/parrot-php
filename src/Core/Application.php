<?php

/**
 * Parrot PHP Framework - Application Core
 *
 * Classe principal da aplicação que orquestra o ciclo de vida completo de uma requisição HTTP.
 * Implementa o padrão RequestHandlerInterface do PSR-15.
 *
 * Fluxo de execução:
 * 1. Carrega configurações do container (PHP-DI)
 * 2. Carrega definições de rotas
 * 3. Carrega middlewares globais
 * 4. Processa a requisição através do pipeline de middlewares
 * 5. Despacha para o router (FastRoute)
 * 6. Retorna a resposta HTTP
 *
 * @see https://www.php-fig.org/psr/psr-15/ PSR-15: HTTP Request Handlers
 * @see https://php-di.org/ PHP-DI - Dependency Injection Container
 * @see https://github.com/nikic/FastRoute FastRoute - Router
 */

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
 * Classe principal da aplicação (Facade do Framework)
 *
 * Atua como o ponto central de coordenação de toda a aplicação,
 * gerenciando rotas, middlewares e o ciclo de vida da requisição.
 *
 * @package App\Core
 */
class Application implements RequestHandlerInterface
{
    /** @var ContainerInterface Container de dependências PHP-DI */
    private ContainerInterface $container;

    /** @var FastRouteRouter Instância do router FastRoute */
    private FastRouteRouter $router;

    /** @var array Lista de middlewares globais a serem executados em cada requisição */
    private array $middlewares = [];

    /**
     * Construtor da aplicação
     *
     * Inicializa o router com base no ambiente (development/production)
     * e define os caminhos para configurações e cache.
     *
     * @param string $basePath Caminho base do projeto (geralmente __DIR__ . '/..')
     * @param string $configPath Caminho para o diretório de configuração
     */
    public function __construct(
        private readonly string $basePath,
        private readonly string $configPath
    ) {
        // Determina o ambiente (development ou production)
        // Em production, o router usará cache para as rotas
        $env = getenv('APP_ENV') ?: 'development';
        $cachePath = rtrim($basePath, '/') . '/cache';

        // Inicializa o router FastRoute com o ambiente e caminho de cache
        $this->router = new FastRouteRouter($env, $cachePath);
    }

    /**
     * Define o container de dependências
     *
     * O container (PHP-DI) é usado para:
     * - Gerenciar instâncias de controllers, models, middlewares
     * - Resolver dependências automaticamente (Injeção de Dependência)
     * - Permitir injeção de Request nos métodos de controller
     *
     * @param ContainerInterface $container Instância do container PHP-DI
     * @return self Retorna a própria instância para method chaining
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;
        $this->router->setContainer($container);

        if ($container->has(\Psr\Http\Message\ResponseFactoryInterface::class)) {
            $this->router->setResponseFactory(
                $container->get(\Psr\Http\Message\ResponseFactoryInterface::class)
            );
        }

        return $this;
    }

    /**
     * Obtém o container de dependências
     *
     * @return ContainerInterface O container PHP-DI configurado
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Carrega configurações do container a partir do arquivo config/container.php
     *
     * Este arquivo contém as definições de serviços que serão gerenciados
     * pelo container PHP-DI, como factories, instâncias e valores.
     *
     * @return self Retorna a própria instância para method chaining
     */
    public function loadContainerConfig(): self
    {
        $configFile = $this->configPath . '/container.php';

        if (file_exists($configFile)) {
            $definitions = require $configFile;

            if (is_array($definitions)) {
                foreach ($definitions as $id => $definition) {
                    $this->container->set($id, $definition);
                }
            }
        }

        return $this;
    }

    /**
     * Carrega as definições de rotas a partir do arquivo config/routes.php
     *
     * O arquivo routes.php retorna um array onde:
     * - A chave é o método HTTP seguido do caminho (ex: "GET /api/usuarios")
     * - O valor é um array com [Controller::class, 'método'] ou [Controller::class, 'método', Middleware::class]
     *
     * @return self Retorna a própria instância para method chaining
     * @see config/routes.php
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
     * Carrega os middlewares globais a partir do arquivo config/middlewares.php
     *
     * Middlewares globais são executados em TODAS as requisições, antes do router.
     * A ordem de execução é importante - o primeiro no array é o primeiro a executar.
     *
     * Ordem padrão:
     * 1. ErrorHandlerMiddleware - trata erros
     * 2. SecurityHeadersMiddleware - adiciona headers de segurança
     * 3. RateLimitMiddleware - limita requisições
     * 4. CorsMiddleware - permite requisições cross-origin
     *
     * @return self Retorna a própria instância para method chaining
     * @see config/middlewares.php
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
     * Adiciona um middleware à pilha global
     *
     * Suporta dois formatos:
     * - Objeto: MiddlewareInterface direto
     * - String: Nome da classe que será resolvida pelo container
     *
     * @param MiddlewareInterface|string $middleware Instância ou nome da classe do middleware
     * @return self Retorna a própria instância para method chaining
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): self
    {
        // Se for uma string (nome da classe), tenta resolver pelo container
        if (is_string($middleware) && isset($this->container)) {
            $middleware = $this->container->get($middleware);
        }

        // Adiciona apenas se for uma instância válida de MiddlewareInterface
        if ($middleware instanceof MiddlewareInterface) {
            $this->middlewares[] = $middleware;
        }

        return $this;
    }

    /**
     * Processa uma requisição HTTP
     *
     * Este é o método principal que implementa a interface RequestHandlerInterface.
     * Executa o pipeline de middlewares e, em seguida, despacha para o router.
     *
     * @param ServerRequestInterface $request Requisição PSR-7
     * @return ResponseInterface Resposta PSR-7
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Se há middlewares, processa através da fila
        if (!empty($this->middlewares)) {
            return $this->processMiddlewareQueue($request);
        }

        // Caso contrário, despacha direto para o router
        return $this->router->handle($request);
    }

    /**
     * Cria e processa a fila de middlewares
     *
     * O MiddlewareQueue implementa o padrão " Onion Pattern" ou "Pipeline",
     * onde cada middleware pode:
     * - Executar lógica antes de passar para o próximo
     * - Passar a requisição para o próximo middleware
     * - Interceptar e retornar uma resposta diferente
     *
     * @param ServerRequestInterface $request Requisição HTTP
     * @return ResponseInterface Resposta HTTP final
     */
    private function processMiddlewareQueue(ServerRequestInterface $request): ResponseInterface
    {
        // Cria a fila de middlewares com os middlewares globais
        $middlewareStack = new MiddlewareQueue($this->middlewares);

        // Define o handler final (o router) que será chamado após todos os middlewares
        $middlewareStack->setHandler($this->router);

        // Inicia o processamento - cada middleware decide se passa para o próximo
        return $middlewareStack->handle($request);
    }

    /**
     * Inicia a aplicação - método de entrada principal
     *
     * Este método deve ser chamado no final do arquivo public/index.php.
     * Ele:
     * 1. Cria uma requisição PSR-7 a partir das superglobais do PHP ($_SERVER, etc.)
     * 2. Configura o tratamento de erros conforme o ambiente
     * 3. Processa a requisição através do handler (middlewares + router)
     * 4. Envia a resposta HTTP de volta ao cliente
     *
     * @return void
     */
    public function run(): void
    {
        $serverRequest = $this->createRequestFromGlobals();

        $this->configureErrorHandling();

        $response = $this->handle($serverRequest);

        $this->sendResponse($response);
    }

    /**
     * Cria uma requisição PSR-7 a partir das superglobais do PHP
     *
     * Converte $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES em um objeto
     * ServerRequestInterface padrão PSR-7. Isso permite que a aplicação
     * funcione de forma independente do servidor HTTP (PHP built-in, Apache, Nginx).
     *
     * @return ServerRequestInterface Requisição PSR-7 pronta para uso
     * @see https://www.php-fig.org/psr/psr-7/ PSR-7: HTTP Message Interfaces
     */
    private function createRequestFromGlobals(): ServerRequestInterface
    {
        // PSR-17 Factory padrão para criar objetos PSR-7
        $psr17Factory = new Psr17Factory();

        // Criador de ServerRequest que converte superglobais PHP
        $serverRequestCreator = new ServerRequestCreator(
            $psr17Factory,  // ServerRequestFactory
            $psr17Factory,  // UriFactory
            $psr17Factory,  // UploadedFileFactory
            $psr17Factory   // StreamFactory
        );

        // Converte $_SERVER, $_GET, $_POST, etc. em ServerRequestInterface
        return $serverRequestCreator->fromGlobals();
    }

    /**
     * Configura o tratamento de erros conforme o ambiente
     *
     * Em development: exibe erros na tela para debug
     * Em production: oculta erros e apenas loga
     *
     * Isso é crucial para segurança - nunca exiba erros em produção!
     *
     * @return void
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
     * Envia a resposta HTTP de volta ao cliente
     *
     * Este método:
     * 1. Define o código de status HTTP (200, 404, 500, etc.)
     * 2. Envia todos os headers da resposta
     * 3. Envia o corpo (body) da resposta
     *
     * @param ResponseInterface $response Resposta PSR-7 a ser enviada
     * @return void
     */
    private function sendResponse(ResponseInterface $response): void
    {
        // Define o código de status HTTP (200, 404, 500, etc.)
        http_response_code($response->getStatusCode());

        header_remove('X-Powered-By');

        // Envia todos os headers um por um
        // O segundo parâmetro 'false' permite headers com o mesmo nome
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        // Envia o corpo da resposta (geralmente JSON)
        echo $response->getBody();
    }
}
