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

class Application implements RequestHandlerInterface
{
    private ContainerInterface $container;

    private FastRouteRouter $router;

    private array $middlewares = [];

    public function __construct(
        private readonly string $basePath,
        private readonly string $configPath
    ) {
        $env = getenv('APP_ENV') ?: 'development';
        $cachePath = rtrim($basePath, '/') . '/cache';

        $this->router = new FastRouteRouter($env, $cachePath);
    }

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

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

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

    public function loadRoutes(): self
    {
        $routesFile = $this->configPath . '/routes.php';

        if (file_exists($routesFile)) {
            $this->router->loadRoutes($routesFile);
        }

        return $this;
    }

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

    public function addMiddleware(MiddlewareInterface|string $middleware): self
    {
        if (is_string($middleware) && isset($this->container)) {
            $middleware = $this->container->get($middleware);
        }

        if ($middleware instanceof MiddlewareInterface) {
            $this->middlewares[] = $middleware;
        }

        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!empty($this->middlewares)) {
            return $this->processMiddlewareQueue($request);
        }

        return $this->router->handle($request);
    }

    private function processMiddlewareQueue(ServerRequestInterface $request): ResponseInterface
    {
        $middlewareStack = new MiddlewareQueue($this->middlewares);

        $middlewareStack->setHandler($this->router);

        return $middlewareStack->handle($request);
    }

    public function run(): void
    {
        $serverRequest = $this->createRequestFromGlobals();

        $this->configureErrorHandling();

        $response = $this->handle($serverRequest);

        $this->sendResponse($response);
    }

    private function createRequestFromGlobals(): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();

        $serverRequestCreator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        return $serverRequestCreator->fromGlobals();
    }

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

    private function sendResponse(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        echo $response->getBody();
    }
}
