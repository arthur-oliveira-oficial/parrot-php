<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - FastRoute Router
 *
 * Implementação de roteamento usando a biblioteca FastRoute.
 * Fornece dispatching de rotas de alta performance com suporte a:
 * - Métodos HTTP: GET, POST, PUT, PATCH, DELETE
 * - Parâmetros dinâmicos: /api/usuarios/{id}
 * - Middlewares por rota
 * - Cache de rotas em produção
 *
 * @see https://github.com/nikic/FastRoute FastRoute
 * @see https://www.php-fig.org/psr/psr-15/ PSR-15: HTTP Request Handlers
 */

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
 * Router Principal usando FastRoute
 *
 * Esta classe é responsável por:
 * - Registrar rotas (GET, POST, PUT, etc.)
 * - Encontrar a rota correspondente à requisição
 * - Dispatchar para o controller correto
 * - Aplicar middlewares específicos da rota
 *
 * @package App\Core
 */
class FastRouteRouter implements RequestHandlerInterface
{
    /** @var ContainerInterface|null Container de dependências para resolver controllers */
    private ?ContainerInterface $container = null;

    /** @var ResponseFactoryInterface|null Fábrica para criar respostas HTTP */
    private ?ResponseFactoryInterface $responseFactory = null;

    /** @var array Armazena todas as rotas cadastradas. Chave: "METODO /caminho", Valor: ['callback' => [...], 'middleware' => ...] */
    private array $rotas = [];

    /** @var array Middlewares que serão aplicados a todas as rotas */
    private array $middlewares = [];

    /** @var string Ambiente atual (development ou production) */
    private string $env = 'development';

    /** @var string Caminho para o diretório de cache */
    private string $cachePath = '';

    /**
     * Construtor do Router
     *
     * @param string $env Ambiente (determina se usa cache)
     * @param string $cachePath Caminho para salvar cache de rotas
     */
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

    /**
     * Adiciona uma rota ao router
     *
     * O parâmetro $destino pode ser:
     * - Array: [Controller::class, 'método'] - chama o método do controller
     * - Array: [Controller::class, 'método', Middleware::class] - com middleware específico
     * - Closure: função anônima que recebe a requisição
     *
     * O caminho pode conter parâmetros dinâmicos:
     * - /api/usuarios/{id} - captura o valor em $request->getAttribute('id')
     *
     * @param string $metodo Método HTTP (GET, POST, PUT, PATCH, DELETE)
     * @param string $caminho Caminho da rota (pode ter parâmetros como {id})
     * @param array|callable $destino Controller[método] ou closure
     * @return self
     */
    public function addRoute(string $metodo, string $caminho, array|callable $destino): self
    {
        // Normaliza o caminho: remove trailing slash
        $caminho = rtrim($caminho, '/');
        if ($caminho === '') {
            $caminho = '/';
        }

        // Chave única no formato "METODO /caminho"
        $chave = strtoupper($metodo) . ' ' . $caminho;

        // Extrai middleware da rota se especificado no formato [Controller, 'metodo', Middleware::class]
        $middlewareRota = null;
        if (is_array($destino) && count($destino) === 3 && is_string($destino[2])) {
            $middlewareRota = $destino[2];
            $destino = [$destino[0], $destino[1]];
        }

        // Armazena a rota com seu callback e middleware
        $this->rotas[$chave] = [
            'callback' => $destino,
            'middleware' => $middlewareRota,
        ];

        return $this;
    }

    /**
     * Carrega rotas de um arquivo PHP
     *
     * O arquivo deve retornar um array onde:
     * - Chave: "METODO /caminho" (ex: "GET /api/usuarios")
     * - Valor: [Controller::class, 'método'] ou [Controller::class, 'método', Middleware::class]
     *
     * @param string $arquivoRotas Caminho para o arquivo de rotas
     * @return self
     */
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

    /**
     * Processa a requisição e encontra a rota correspondente
     *
     * Este é o método principal do Router (implementa PSR-15 RequestHandlerInterface).
     * Fluxo:
     * 1. Obtém método HTTP e caminho da requisição
     * 2. Cria o dispatcher FastRoute (com cache em produção)
     * 3. Dispara a rota para encontrar o handler
     * 4. Se encontrada: extrai parâmetros, aplica middlewares e dispatcha
     * 5. Se não encontrada: retorna erro 404
     * 6. Se método não permitido: retorna erro 405
     *
     * @param ServerRequestInterface $request Requisição HTTP
     * @return ResponseInterface Resposta HTTP
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Obtém método HTTP e caminho da URI
        $metodo = $request->getMethod();
        $caminho = $this->normalizarCaminho($request->getUri()->getPath());

        // Verifica se há closures nas rotas (não pode usar cache se houver)
        $temClosure = $this->verificarTemClosure();

        // Em produção, usa cache do FastRoute para melhor performance
        if ($this->env === 'production' && $this->cachePath !== '' && !$temClosure) {
            // CacheDispatcher:.compila rotas uma vez e salva em arquivo
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
            // SimpleDispatcher:compila rotas a cada requisição (útil em desenvolvimento)
            $dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) {
                foreach ($this->rotas as $chave => $dados) {
                    [$metodoRota, $caminhoRota] = explode(' ', $chave, 2);
                    $r->addRoute($metodoRota, $caminhoRota, $dados);
                }
            });
        }

        // Dispara a rota: método HTTP + caminho
        $routeInfo = $dispatcher->dispatch($metodo, $caminho);

        // Analisa o resultado do dispatch
        switch ($routeInfo[0]) {
            case GroupCountBasedDispatcher::FOUND:
                // Rota encontrada!
                $handler = $routeInfo[1]; // Controller[método] ou closure
                $vars = $routeInfo[2] ?? []; // Parâmetros da URL {id}

                // Adiciona parâmetros como atributos da requisição
                // Assim o controller pode acessar via $request->getAttribute('id')
                foreach ($vars as $nome => $valor) {
                    $request = $request->withAttribute($nome, $valor);
                }

                $middlewareRota = $routeInfo[3] ?? null;

                // Dispatch para o controller, aplicando middlewares
                return $this->dispatch($handler, $request, $middlewareRota);

            case GroupCountBasedDispatcher::METHOD_NOT_ALLOWED:
                // Rota existe mas método HTTP não é permitido
                // Ex: PUT /recurso quando só允许 GET e POST
                $allowedMethods = implode(', ', $routeInfo[1]);
                $response = $this->criarRespostaErro('Método não permitido. Esperado: ' . $allowedMethods, 405);
                return $response->withHeader('Allow', $allowedMethods);

            default:
                // Nenhuma rota encontrada para este caminho
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

    /**
     * Dispara a execução para o controller ou closure
     *
     * Este método:
     * 1. Verifica se é uma closure (função anônima) ou controller
     * 2. Se controller: cria instância e configura middlewares da rota
     * 3. Cria uma fila de middlewares com o handler final
     * 4. Executa a fila - cada middleware processa e passa para o próximo
     *
     * @param array $handler Controller[método] ou closure
     * @param ServerRequestInterface $request Requisição HTTP
     * @param string|null $middlewareRota Middleware específico desta rota
     * @return ResponseInterface Resposta HTTP
     */
    private function dispatch(array $handler, ServerRequestInterface $request, ?string $middlewareRota = null): ResponseInterface
    {
        // Se for uma closure (função anônima), executa diretamente
        if (is_callable($handler)) {
            return $handler($request);
        }

        // Extrai o middleware da rota (pode estar no array ou como parâmetro)
        $middlewareRotaData = $handler['middleware'] ?? null;
        $callback = $handler['callback'];

        if ($middlewareRota === null && $middlewareRotaData !== null) {
            $middlewareRota = $middlewareRotaData;
        }

        // Separa a classe do controller e o método
        [$controllerClass, $metodo] = $callback;

        // Começa com os middlewares globais
        $middlewaresToApply = $this->middlewares;

        // Adiciona o middleware específico desta rota (se houver)
        if ($middlewareRota !== null && $this->container !== null) {
            if ($this->container->has($middlewareRota)) {
                $middlewaresToApply[] = $this->container->get($middlewareRota);
            }
        }

        // Cria o handler final que executará o controller
        $finalHandler = new FastRouteControllerHandler(
            $controllerClass,
            $metodo,
            $this->container,
            $this->responseFactory
        );

        // Cria a fila de middlewares com o controller como destino final
        $queue = new MiddlewareQueue($middlewaresToApply, $finalHandler);

        // Inicia o processamento - a fila chamará o controller por último
        return $queue->handle($request);
    }
}

/**
 * Handler para executar métodos de Controller
 *
 * Esta classe é responsável por:
 * - Criar a instância do controller (com injeção de dependência)
 * - Invocar o método do controller (com suporte a parâmetros automática)
 * - Tratar erros (controller ou método não encontrado)
 *
 * Usa a biblioteca Invoker do PHP-DI para chamar métodos com parâmetros automáticos,
 * injetando automaticamente o objeto Request quando o método aceita esse parâmetro.
 *
 * @see https://github.com/PHP-DI/Invoker Invoker - Callable parameter resolver
 */
class FastRouteControllerHandler implements RequestHandlerInterface
{
    /** @var Invoker|null Instância do Invoker para chamar métodos com parâmetros automáticos */
    private ?Invoker $invoker = null;

    /**
     * Construtor
     *
     * @param string $controllerClass Nome completo da classe do controller
     * @param string $metodo Nome do método a ser chamado
     * @param ContainerInterface|null $container Container para resolver dependências
     * @param ResponseFactoryInterface|null $responseFactory Fábrica para criar respostas de erro
     */
    public function __construct(
        private string $controllerClass,
        private string $metodo,
        private ?ContainerInterface $container,
        private ?ResponseFactoryInterface $responseFactory = null
    ) {}

    /**
     * Executa o controller
     *
     * Implementa PSR-15 RequestHandlerInterface.
     * Fluxo: verifica classe → cria controller → verifica método → invoca
     *
     * @param ServerRequestInterface $request Requisição HTTP
     * @return ResponseInterface Resposta HTTP
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Verifica se a classe do controller existe
        if (!class_exists($this->controllerClass)) {
            return $this->criarRespostaErro('Controller não encontrado', 500);
        }

        // Cria a instância do controller (com dependências resolvidas)
        $controller = $this->criarController();

        // Verifica se o método existe no controller
        if (!method_exists($controller, $this->metodo)) {
            return $this->criarRespostaErro('Método não encontrado', 500);
        }

        // Chama o método do controller (com injeção automática de parâmetros)
        return $this->invocarMetodo($controller, $request);
    }

    /**
     * Invoca o método do controller com parâmetros automáticos
     *
     * Usa o Invoker do PHP-DI para:
     * - Injetar automaticamente o objeto Request no método
     * - Resolver outras dependências do método via container
     * - Tratar erros de invocação
     *
     * @param object $controller Instância do controller
     * @param ServerRequestInterface $request Requisição HTTP
     * @return ResponseInterface Resposta HTTP
     */
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
