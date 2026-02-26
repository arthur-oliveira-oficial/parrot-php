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

/**
 * Adaptador FastRoute que implementa PSR-15.
 * Substitui o Router customizado com regex por nikic/fast-route.
 */
class FastRouteRouter implements RequestHandlerInterface
{
    private ?ContainerInterface $container = null;
    private ?ResponseFactoryInterface $responseFactory = null;

    /**
     * @var array<string, array{callback: array{0: string, 1: string}, middleware?: string}>
     */
    private array $rotas = [];

    /**
     * Middlewares registrados globalmente.
     *
     * @var array<int, MiddlewareInterface>
     */
    private array $middlewares = [];

    /**
     * Ambiente da aplicação.
     */
    private string $env = 'development';

    /**
     * Caminho para o diretório de cache.
     */
    private string $cachePath = '';

    public function __construct(
        string $env = 'development',
        string $cachePath = ''
    ) {
        $this->env = $env;
        $this->cachePath = $cachePath;
    }

    /**
     * Define o container de dependências.
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Define a fábrica de respostas PSR-17.
     */
    public function setResponseFactory(ResponseFactoryInterface $responseFactory): self
    {
        $this->responseFactory = $responseFactory;

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

        // Suporta formato com middleware: [Controller, 'metodo', Middleware::class]
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
     * Normaliza o caminho da rota.
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

        // Detecta se há closures nas rotas - o cachedDispatcher não suporta closures
        $temClosure = $this->verificarTemClosure();

        // Usa cachedDispatcher em produção, simpleDispatcher em desenvolvimento
        // Se houver closures, força simpleDispatcher pois var_export() não suporta closures
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

                // Adiciona parâmetros à requisição
                foreach ($vars as $nome => $valor) {
                    $request = $request->withAttribute($nome, $valor);
                }

                $middlewareRota = $routeInfo[3] ?? null;

                return $this->dispatch($handler, $request, $middlewareRota);

            case GroupCountBasedDispatcher::METHOD_NOT_ALLOWED:
                // $routeInfo[1] contém um array com os métodos permitidos
                $allowedMethods = implode(', ', $routeInfo[1]);
                $response = $this->criarRespostaErro('Método não permitido. Esperado: ' . $allowedMethods, 405);
                return $response->withHeader('Allow', $allowedMethods);

            default: // Dispatcher::NOT_FOUND
                return $this->criarRespostaErro('Rota não encontrada', 404);
        }
    }

    /**
     * Verifica se alguma rota utiliza closure.
     * O cachedDispatcher do FastRoute usa var_export() que não suporta closures.
     */
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

    /**
     * Cria uma resposta de erro usando a fábrica PSR-17.
     */
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

        // Fallback se não houver fábrica
        throw new \RuntimeException("Fábrica PSR-17 não configurada no Router.");
    }

    /**
     * Despacha a requisição para o controller, aplicando middlewares.
     */
    private function dispatch(array $handler, ServerRequestInterface $request, ?string $middlewareRota = null): ResponseInterface
    {
        // Suporte a closures/callables
        if (is_callable($handler)) {
            return $handler($request);
        }

        $middlewareRotaData = $handler['middleware'] ?? null;
        $callback = $handler['callback'];

        // Usa middleware da rota se não foi passado explicitamente
        if ($middlewareRota === null && $middlewareRotaData !== null) {
            $middlewareRota = $middlewareRotaData;
        }

        [$controllerClass, $metodo] = $callback;

        // Coleta todos os middlewares a serem aplicados
        $middlewaresToApply = $this->middlewares;

        // Adiciona middleware específico da rota se existir
        if ($middlewareRota !== null && $this->container !== null) {
            if ($this->container->has($middlewareRota)) {
                $middlewaresToApply[] = $this->container->get($middlewareRota);
            }
        }

        // Cria um handler final que chama o controller
        $finalHandler = new FastRouteControllerHandler(
            $controllerClass,
            $metodo,
            $this->container,
            $this->responseFactory
        );

        // Usa MiddlewareQueue
        $queue = new MiddlewareQueue($middlewaresToApply, $finalHandler);

        return $queue->handle($request);
    }
}

/**
 * Handler para instanciar e chamar o controller.
 * Resolve dependências via container com autowiring usando php-di/invoker.
 */
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
        // Verifica se o controller existe
        if (!class_exists($this->controllerClass)) {
            return $this->criarRespostaErro('Controller não encontrado', 500);
        }

        // Cria instância do controller com suporte a autowiring
        $controller = $this->criarController();

        // Verifica se o método existe
        if (!method_exists($controller, $this->metodo)) {
            return $this->criarRespostaErro('Método não encontrado', 500);
        }

        // Usa o Invoker para chamar o método com injeção de dependências
        return $this->invocarMetodo($controller, $request);
    }

    /**
     * Invoca o método do controller com suporte a injeção de dependências.
     */
    private function invocarMetodo(object $controller, ServerRequestInterface $request): ResponseInterface
    {
        // Se o container e o Invoker estiverem disponíveis, usa injeção de dependência
        if ($this->container !== null && class_exists(Invoker::class)) {
            if ($this->invoker === null) {
                // Passando null, o Invoker carrega a cadeia padrão de resolvers
                // (incluindo o AssociativeArrayResolver e o TypeHintContainerResolver)
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

        // Fallback: chamada direta
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

    /**
     * Cria uma resposta de erro usando a fábrica PSR-17.
     */
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
