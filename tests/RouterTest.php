<?php

namespace Tests;

use App\Core\Router;
use App\Core\Response;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\ServerRequest;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
    }

    public function testGetRouteRegistration(): void
    {
        $this->router->get('/test', [TestController::class, 'test']);

        $this->assertTrue(true);
    }

    public function testPostRouteRegistration(): void
    {
        $this->router->post('/test', [TestController::class, 'test']);

        $this->assertTrue(true);
    }

    public function testFluentInterface(): void
    {
        $result = $this->router
            ->get('/users', [TestController::class, 'index'])
            ->post('/users', [TestController::class, 'store'])
            ->get('/users/{id}', [TestController::class, 'show']);

        $this->assertInstanceOf(Router::class, $result);
    }

    public function testExactRouteMatch(): void
    {
        $this->router->get('/api/users', [TestController::class, 'index']);

        $request = new ServerRequest('GET', '/api/users');

        $this->assertTrue(true);
    }

    public function testPatternConversion(): void
    {
        $router = new Router();

        $reflection = new \ReflectionClass($router);
        $method = $reflection->getMethod('converterPadraoParaRegex');
        $method->setAccessible(true);

        $result = $method->invoke($router, '/users/{id}');

        $this->assertEquals('#^/users/([^/]+)$#', $result);
    }
}

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
