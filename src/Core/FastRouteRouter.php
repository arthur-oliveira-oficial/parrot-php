<?php

declare(strict_types=1);

namespace App\Core;

use FastRoute\RouteCollector;
use FastRoute\Dispatcher\GroupCountBased as GroupCountBasedDispatcher;
use Invoker\Invoker;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

class FastRouteRouter implements RequestHandlerInterface
{
    private ?ContainerInterface $container = null;
    private ?ResponseFactoryInterface $responseFactory = null;

    private array $rotas = [];

    private array $middlewares = [];

    private string $env = 'development';

    private string $cachePath = '';

    public function __construct(
        string $env = 'development',
        string $cachePath = ''
    ) {
        $this->env = $env;
        $this->cachePath = $cachePath;
    }

    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;

        return $this;
    }

    public function setResponseFactory(ResponseFactoryInterface $responseFactory): self
    {
        $this->responseFactory = $responseFactory;

        return $this;
    }

    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function addMiddlewareClass(string $middlewareClass): self
    {
        if ($this->container !== null && $this->container->has($middlewareClass)) {
            $this->middlewares[] = $this->container->get($middlewareClass);
        }

        return $this;
    }

    public function get(string $caminho, array|callable $destino): self
    {
        return $this->addRoute('GET', $caminho, $destino);
    }

    public function post(string $caminho, array|callable $destino): self
    {
        return $this->addRoute('POST', $caminho, $destino);
    }

    public function put(string $caminho, array|callable $destino): self
    {
        return $this->addRoute('PUT', $caminho, $destino);
    }

    public function patch(string $caminho, array|callable $destino): self
    {
        return $this->addRoute('PATCH', $caminho, $destino);
    }

    public function delete(string $caminho, array|callable $destino): self
    {
        return $this->addRoute('DELETE', $caminho, $destino);
    }

    public function any(string $caminho, array|callable $destino): self
    {
        $metodos = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($metodos as $metodo) {
            $this->addRoute($metodo, $caminho, $destino);
        }

        return $this;
    }

    public function addRoute(string $metodo, string $caminho, array|callable $destino): self
    {
        $caminho = rtrim($caminho, '/');
        if ($caminho === '') {
            $caminho = '/';
        }

        $chave = strtoupper($metodo) . ' ' . $caminho;

        $middlewareRota = null;
        if (is_array($destino) && count($destino) === 3 && is_string($destino[2])) {
            $middlewareRota = $destino[2];
            $destino = [$destino[0], $destino[1]];
        }

        $this->rotas[$chave] = [
            'callback' => $destino,
            'middleware' => $middlewareRota,
        ];

        return $this;
    }

    public function loadRoutes(string $arquivoRotas): self
    {
        $rotas = require $arquivoRotas;

        foreach ($rotas as $chave => $destino) {
            [$metodo, $caminho] = explode(' ', $chave, 2);
            $this->addRoute($metodo, $caminho, $destino);
        }

        return $this;
    }

    private function normalizarCaminho(string $caminho): string
    {
        $caminho = rtrim($caminho, '/');
        if ($caminho === '') {
            $caminho = '/';
        }
        return $caminho;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $metodo = $request->getMethod();
        $caminho = $this->normalizarCaminho($request->getUri()->getPath());

        $temClosure = $this->verificarTemClosure();

        if ($this->env === 'production' && $this->cachePath !== '' && !$temClosure) {
            $dispatcher = \FastRoute\cachedDispatcher(function(\FastRoute\RouteCollector $r) {
                foreach ($this->rotas as $chave => $dados) {
                    [$metodoRota, $caminhoRota] = explode(' ', $chave, 2);
                    $r->addRoute($metodoRota, $caminhoRota, $dados);
                }
            }, [
                'cacheFile' => $this->cachePath . '/routes.php',
                'cacheDisabled' => false,
            ]);
        } else {
            $dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) {
                foreach ($this->rotas as $chave => $dados) {
                    [$metodoRota, $caminhoRota] = explode(' ', $chave, 2);
                    $r->addRoute($metodoRota, $caminhoRota, $dados);
                }
            });
        }

        $routeInfo = $dispatcher->dispatch($metodo, $caminho);

        switch ($routeInfo[0]) {
            case GroupCountBasedDispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2] ?? [];

                foreach ($vars as $nome => $valor) {
                    $request = $request->withAttribute($nome, $valor);
                }

                $middlewareRota = $routeInfo[3] ?? null;

                return $this->dispatch($handler, $request, $middlewareRota);

            case GroupCountBasedDispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = implode(', ', $routeInfo[1]);
                $response = $this->criarRespostaErro('Método não permitido. Esperado: ' . $allowedMethods, 405);
                return $response->withHeader('Allow', $allowedMethods);

            default:
                return $this->criarRespostaErro('Rota não encontrada', 404);
        }
    }

    private function verificarTemClosure(): bool
    {
        foreach ($this->rotas as $dados) {
            $callback = $dados['callback'] ?? null;
            if ($callback instanceof \Closure) {
                return true;
            }
            if (is_array($callback)) {
                foreach ($callback as $item) {
                    if ($item instanceof \Closure) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function criarRespostaErro(string $mensagem, int $statusCode): ResponseInterface
    {
        if ($this->responseFactory !== null) {
            $response = $this->responseFactory->createResponse($statusCode);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'error' => $mensagem,
                'status' => $statusCode,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $response;
        }

        throw new \RuntimeException("Fábrica PSR-17 não configurada no Router.");
    }

    private function dispatch(array $handler, ServerRequestInterface $request, ?string $middlewareRota = null): ResponseInterface
    {
        if (is_callable($handler)) {
            return $handler($request);
        }

        $middlewareRotaData = $handler['middleware'] ?? null;
        $callback = $handler['callback'];

        if ($middlewareRota === null && $middlewareRotaData !== null) {
            $middlewareRota = $middlewareRotaData;
        }

        [$controllerClass, $metodo] = $callback;

        $middlewaresToApply = $this->middlewares;

        if ($middlewareRota !== null && $this->container !== null) {
            if ($this->container->has($middlewareRota)) {
                $middlewaresToApply[] = $this->container->get($middlewareRota);
            }
        }

        $finalHandler = new FastRouteControllerHandler(
            $controllerClass,
            $metodo,
            $this->container,
            $this->responseFactory
        );

        $queue = new MiddlewareQueue($middlewaresToApply, $finalHandler);

        return $queue->handle($request);
    }
}

class FastRouteControllerHandler implements RequestHandlerInterface
{
    private ?Invoker $invoker = null;

    public function __construct(
        private string $controllerClass,
        private string $metodo,
        private ?ContainerInterface $container,
        private ?ResponseFactoryInterface $responseFactory = null
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!class_exists($this->controllerClass)) {
            return $this->criarRespostaErro('Controller não encontrado', 500);
        }

        $controller = $this->criarController();

        if (!method_exists($controller, $this->metodo)) {
            return $this->criarRespostaErro('Método não encontrado', 500);
        }

        return $this->invocarMetodo($controller, $request);
    }

    private function invocarMetodo(object $controller, ServerRequestInterface $request): ResponseInterface
    {
        if ($this->container !== null && class_exists(Invoker::class)) {
            if ($this->invoker === null) {
                $this->invoker = new Invoker(null, $this->container);
            }

            try {
                return $this->invoker->call([$controller, $this->metodo], [
                    'request' => $request,
                ]);
            } catch (\Exception $e) {
                return $this->criarRespostaErro('Erro ao executar método: ' . $e->getMessage(), 500);
            }
        }

        return $controller->{$this->metodo}($request);
    }

    private function criarController(): object
    {
        if ($this->container !== null) {
            try {
                return $this->container->get($this->controllerClass);
            } catch (\Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface $e) {
            }
        }

        return new $this->controllerClass();
    }

    private function criarRespostaErro(string $mensagem, int $statusCode): ResponseInterface
    {
        if ($this->responseFactory !== null) {
            $response = $this->responseFactory->createResponse($statusCode);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'error' => $mensagem,
                'status' => $statusCode,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $response;
        }

        throw new \RuntimeException("Fábrica PSR-17 não configurada no Router.");
    }
}
