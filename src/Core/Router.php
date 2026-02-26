<?php

namespace App\Core;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Sistema de roteamento do framework.
 * Suporta parâmetros dinâmicos, métodos HTTP e middleware por rota.
 */
class Router implements RequestHandlerInterface
{
    /**
     * Rotas registradas no formato ['METODO /caminho' => [Controller::class, 'metodo']]
     * Ou com middleware: ['METODO /caminho' => [Controller::class, 'metodo', 'middleware']]
     *
     * @var array<string, array{0: string, 1: string, 2?: string}>
     */
    private array $rotas = [];

    /**
     * Parâmetros extraídos da URL atual.
     */
    private array $params = [];

    /**
     * Container de dependências (opcional).
     */
    private ?ContainerInterface $container = null;

    /**
     * Middlewares registrados globalmente.
     *
     * @var array<int, MiddlewareInterface>
     */
    private array $middlewares = [];

    /**
     * Define o container de dependências.
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Adiciona um middleware global ao router.
     */
    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Adiciona um middleware via nome da classe (busca do container).
     */
    public function addMiddlewareClass(string $middlewareClass): self
    {
        if ($this->container !== null && $this->container->has($middlewareClass)) {
            $this->middlewares[] = $this->container->get($middlewareClass);
        }

        return $this;
    }

    /**
     * Registra uma rota GET.
     */
    public function get(string $caminho, array|callable $destino): self
    {
        return $this->addRoute('GET', $caminho, $destino);
    }

    /**
     * Registra uma rota POST.
     */
    public function post(string $caminho, array|callable $destino): self
    {
        return $this->addRoute('POST', $caminho, $destino);
    }

    /**
     * Registra uma rota PUT.
     */
    public function put(string $caminho, array|callable $destino): self
    {
        return $this->addRoute('PUT', $caminho, $destino);
    }

    /**
     * Registra uma rota PATCH.
     */
    public function patch(string $caminho, array|callable $destino): self
    {
        return $this->addRoute('PATCH', $caminho, $destino);
    }

    /**
     * Registra uma rota DELETE.
     */
    public function delete(string $caminho, array|callable $destino): self
    {
        return $this->addRoute('DELETE', $caminho, $destino);
    }

    /**
     * Registra uma rota para qualquer método HTTP.
     */
    public function any(string $caminho, array|callable $destino): self
    {
        $metodos = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($metodos as $metodo) {
            $this->addRoute($metodo, $caminho, $destino);
        }

        return $this;
    }

    /**
     * Adiciona uma rota ao router.
     */
    public function addRoute(string $metodo, string $caminho, array|callable $destino): self
    {
        $caminho = rtrim($caminho, '/');
        if ($caminho === '') {
            $caminho = '/';
        }

        $chave = strtoupper($metodo) . ' ' . $caminho;

        // Pré-compila regex se o caminho tem parâmetros dinâmicos
        $regexCompilado = null;
        $nomesParametros = [];
        $middlewareRota = null;

        if (is_array($caminho)) {
            // Não - $caminho é string, verificamos se tem parâmetros
        }

        if (strpos($caminho, '{') !== false) {
            $regexCompilado = $this->converterPadraoParaRegex($caminho);
            preg_match_all('/\{(\w+)\}/', $caminho, $nomesParametros);
            $nomesParametros = $nomesParametros[1];
        }

        // Suporta formato com middleware: [Controller, 'metodo', Middleware::class]
        // Apenas para arrays, não para callables
        if (is_array($destino) && count($destino) === 3 && is_string($destino[2])) {
            $middlewareRota = $destino[2];
            $destino = [$destino[0], $destino[1]];
        }

        // Guarda: [destino, regex, nomes_parametros, middleware]
        $this->rotas[$chave] = [
            $destino,
            $regexCompilado,
            $nomesParametros,
            $middlewareRota
        ];

        return $this;
    }

    /**
     * Carrega rotas de um arquivo de configuração.
     */
    public function loadRoutes(string $arquivoRotas): self
    {
        $rotas = require $arquivoRotas;

        foreach ($rotas as $chave => $destino) {
            // Formato esperado: 'GET /api/users' => [Controller::class, 'index']
            [$metodo, $caminho] = explode(' ', $chave, 2);
            $this->addRoute($metodo, $caminho, $destino);
        }

        return $this;
    }

    /**
     * Normaliza o caminho da rota (corrige problema da raiz).
     */
    private function normalizarCaminho(string $caminho): string
    {
        $caminho = rtrim($caminho, '/');
        if ($caminho === '') {
            $caminho = '/';
        }
        return $caminho;
    }

    /**
     * Processa a requisição e retorna a resposta.
     * Implementa RequestHandlerInterface do PSR-15.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $metodo = $request->getMethod();
        $caminho = $this->normalizarCaminho($request->getUri()->getPath());

        // Tenta encontrar rota exata primeiro
        $chave = $metodo . ' ' . $caminho;

        if (isset($this->rotas[$chave])) {
            $rota = $this->rotas[$chave];
            $middlewareRota = $rota[3] ?? null;
            return $this->dispatch($rota, $request, $middlewareRota);
        }

        // Tenta encontrar rota com parâmetros
        $rotaEncontrada = $this->matchRotaComParametros($metodo, $caminho);

        if ($rotaEncontrada !== null) {
            $middlewareRota = $rotaEncontrada[3] ?? null;

            // Adiciona parâmetros à requisição
            foreach ($this->params as $nome => $valor) {
                $request = $request->withAttribute($nome, $valor);
            }

            return $this->dispatch($rotaEncontrada, $request, $middlewareRota);
        }

        // Rota não encontrada
        return Response::error('Rota não encontrada', 404);
    }

    /**
     * Tenta fazer match de uma rota com parâmetros dinâmicos.
     */
    private function matchRotaComParametros(string $metodo, string $caminho): ?array
    {
        foreach ($this->rotas as $chaveRota => $rotaData) {
            [$metodoRota, $caminhoRota] = explode(' ', $chaveRota, 2);

            if ($metodoRota !== $metodo) {
                continue;
            }

            // Usa regex pré-compilado se existir
            $regex = $rotaData[1] ?? null;
            $nomesParametros = $rotaData[2] ?? [];

            if ($regex === null) {
                continue; // Rota sem parâmetros, já foi tratada no handle()
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

    /**
     * Converte padrão de rota (ex: /users/{id}) para regex.
     */
    private function converterPadraoParaRegex(string $padrao): string
    {
        // Substitui {param} por regex que captura qualquer coisa exceto /
        $regex = preg_replace('/\{(\w+)\}/', '([^/]+)', $padrao);

        return '#^' . $regex . '$#';
    }

    /**
     * Despacha a requisição para o controller, aplicando middlewares.
     */
    private function dispatch(array $rotaData, ServerRequestInterface $request, ?string $middlewareRota = null): ResponseInterface
    {
        // Suporte a closures/callables
        if (is_callable($rotaData[0])) {
            return $rotaData[0]($request);
        }

        [$destino, $regex, $nomesParams, $middlewareRotaData] = $rotaData;

        // Usa middleware da rota se não foi passado explicitamente
        if ($middlewareRota === null && $middlewareRotaData !== null) {
            $middlewareRota = $middlewareRotaData;
        }

        [$controllerClass, $metodo] = $destino;

        // Coleta todos os middlewares a serem aplicados
        $middlewaresToApply = $this->middlewares;

        // Adiciona middleware específico da rota se existir
        if ($middlewareRota !== null && $this->container !== null) {
            if ($this->container->has($middlewareRota)) {
                $middlewaresToApply[] = $this->container->get($middlewareRota);
            }
        }

        // Cria um handler final que chama o controller
        $finalHandler = new ControllerHandler($controllerClass, $metodo, $this->container);

        // Usa MiddlewareQueue em vez de classes anónimas
        $queue = new MiddlewareQueue($middlewaresToApply, $finalHandler);

        return $queue->handle($request);
    }
}

/**
 * Handler para instanciar e chamar o controller.
 * Resolve dependências via container com autowiring.
 */
class ControllerHandler implements RequestHandlerInterface
{
    public function __construct(
        private string $controllerClass,
        private string $metodo,
        private ?ContainerInterface $container
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Verifica se o controller existe
        if (!class_exists($this->controllerClass)) {
            return Response::error('Controller não encontrado', 500);
        }

        // Cria instância do controller com suporte a autowiring
        $controller = $this->criarController();

        // Verifica se o método existe
        if (!method_exists($controller, $this->metodo)) {
            return Response::error('Método não encontrado', 500);
        }

        // Chama o método do controller
        return $controller->{$this->metodo}($request);
    }

    /**
     * Cria instância do controller resolvendo dependências.
     */
    private function criarController(): object
    {
        if ($this->container !== null) {
            try {
                return $this->container->get($this->controllerClass);
            } catch (\Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface $e) {
                // Fallback para new direto se o autowiring falhar
            }
        }

        return new $this->controllerClass();
    }
}
