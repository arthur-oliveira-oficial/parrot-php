<?php

namespace App\Core;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

class Router implements RequestHandlerInterface
{
    private array $rotas = [];

    private array $params = [];

    private ?ContainerInterface $container = null;

    private array $middlewares = [];

    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;

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

        $regexCompilado = null;
        $nomesParametros = [];
        $middlewareRota = null;

        if (is_array($caminho)) {
        }

        if (strpos($caminho, '{') !== false) {
            $regexCompilado = $this->converterPadraoParaRegex($caminho);
            preg_match_all('/\{(\w+)\}/', $caminho, $nomesParametros);
            $nomesParametros = $nomesParametros[1];
        }

        if (is_array($destino) && count($destino) === 3 && is_string($destino[2])) {
            $middlewareRota = $destino[2];
            $destino = [$destino[0], $destino[1]];
        }

        $this->rotas[$chave] = [
            $destino,
            $regexCompilado,
            $nomesParametros,
            $middlewareRota
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

        $chave = $metodo . ' ' . $caminho;

        if (isset($this->rotas[$chave])) {
            $rota = $this->rotas[$chave];
            $middlewareRota = $rota[3] ?? null;
            return $this->dispatch($rota, $request, $middlewareRota);
        }

        $rotaEncontrada = $this->matchRotaComParametros($metodo, $caminho);

        if ($rotaEncontrada !== null) {
            $middlewareRota = $rotaEncontrada[3] ?? null;

            foreach ($this->params as $nome => $valor) {
                $request = $request->withAttribute($nome, $valor);
            }

            return $this->dispatch($rotaEncontrada, $request, $middlewareRota);
        }

        return Response::error('Rota não encontrada', 404);
    }

    private function matchRotaComParametros(string $metodo, string $caminho): ?array
    {
        foreach ($this->rotas as $chaveRota => $rotaData) {
            [$metodoRota, $caminhoRota] = explode(' ', $chaveRota, 2);

            if ($metodoRota !== $metodo) {
                continue;
            }

            $regex = $rotaData[1] ?? null;
            $nomesParametros = $rotaData[2] ?? [];

            if ($regex === null) {
                continue;
            }

            if (preg_match($regex, $caminho, $matches)) {
                $this->params = [];

                foreach ($nomesParametros as $indice => $nome) {
                    $this->params[$nome] = $matches[$indice + 1];
                }

                return $rotaData;
            }
        }

        return null;
    }

    private function converterPadraoParaRegex(string $padrao): string
    {
        $regex = preg_replace('/\{(\w+)\}/', '([^/]+)', $padrao);

        return '#^' . $regex . '$#';
    }

    private function dispatch(array $rotaData, ServerRequestInterface $request, ?string $middlewareRota = null): ResponseInterface
    {
        if (is_callable($rotaData[0])) {
            return $rotaData[0]($request);
        }

        [$destino, $regex, $nomesParams, $middlewareRotaData] = $rotaData;

        if ($middlewareRota === null && $middlewareRotaData !== null) {
            $middlewareRota = $middlewareRotaData;
        }

        [$controllerClass, $metodo] = $destino;

        $middlewaresToApply = $this->middlewares;

        if ($middlewareRota !== null && $this->container !== null) {
            if ($this->container->has($middlewareRota)) {
                $middlewaresToApply[] = $this->container->get($middlewareRota);
            }
        }

        $finalHandler = new ControllerHandler($controllerClass, $metodo, $this->container);

        $queue = new MiddlewareQueue($middlewaresToApply, $finalHandler);

        return $queue->handle($request);
    }
}

class ControllerHandler implements RequestHandlerInterface
{
    public function __construct(
        private string $controllerClass,
        private string $metodo,
        private ?ContainerInterface $container
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!class_exists($this->controllerClass)) {
            return Response::error('Controller não encontrado', 500);
        }

        $controller = $this->criarController();

        if (!method_exists($controller, $this->metodo)) {
            return Response::error('Método não encontrado', 500);
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
}
