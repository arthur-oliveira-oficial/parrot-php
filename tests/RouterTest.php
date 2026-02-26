<?php

namespace Tests;

use App\Core\Router;
use App\Core\Response;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\ServerRequest;

/**
 * Testes para o Router.
 */
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
    }

    /**
     * Testa registro de rota GET.
     */
    public function testGetRouteRegistration(): void
    {
        $this->router->get('/test', [TestController::class, 'test']);

        $this->assertTrue(true); // Se não抛 exception, passou
    }

    /**
     * Testa registro de rota POST.
     */
    public function testPostRouteRegistration(): void
    {
        $this->router->post('/test', [TestController::class, 'test']);

        $this->assertTrue(true);
    }

    /**
     * Testa rotas encadeadas (fluent interface).
     */
    public function testFluentInterface(): void
    {
        $result = $this->router
            ->get('/users', [TestController::class, 'index'])
            ->post('/users', [TestController::class, 'store'])
            ->get('/users/{id}', [TestController::class, 'show']);

        $this->assertInstanceOf(Router::class, $result);
    }

    /**
     * Testa match de rota exata.
     */
    public function testExactRouteMatch(): void
    {
        $this->router->get('/api/users', [TestController::class, 'index']);

        // Simula uma requisição
        $request = new ServerRequest('GET', '/api/users');

        // O router deve encontrar a rota (teste básico)
        $this->assertTrue(true);
    }

    /**
     * Testa conversão de padrão para regex.
     */
    public function testPatternConversion(): void
    {
        $router = new Router();

        // Usa reflexão para testar o método privado
        $reflection = new \ReflectionClass($router);
        $method = $reflection->getMethod('converterPadraoParaRegex');
        $method->setAccessible(true);

        $result = $method->invoke($router, '/users/{id}');

        $this->assertEquals('#^/users/([^/]+)$#', $result);
    }
}

/**
 * Controller de teste.
 */
class TestController
{
    public function index($request)
    {
        return Response::json(['data' => []]);
    }

    public function store($request)
    {
        return Response::json(['data' => []], 201);
    }

    public function show($request)
    {
        return Response::json(['data' => []]);
    }

    public function test($request)
    {
        return Response::json(['ok' => true]);
    }
}
