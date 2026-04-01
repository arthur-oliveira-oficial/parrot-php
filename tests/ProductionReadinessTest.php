<?php

declare(strict_types=1);

namespace Tests;

use App\Cache\ArrayKeyValueStore;
use App\Cache\KeyValueStoreInterface;
use App\Core\Application;
use App\Models\TokenRevogado;
use DI\ContainerBuilder;

class ProductionReadinessTest extends TestCase
{
    public function testCacheAutoFazFallbackSeguroQuandoRedisNaoEstaDisponivel(): void
    {
        $container = $this->criarContainerComEnv([
            'APP_ENV' => 'development',
            'CACHE_STORE' => 'auto',
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PORT' => '6390',
            'REDIS_PASSWORD' => '',
        ]);

        $store = $container->get(KeyValueStoreInterface::class);

        $this->assertInstanceOf(ArrayKeyValueStore::class, $store);
    }

    public function testProducaoExigeCacheDistribuido(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não é permitido em produção');

        $container = $this->criarContainerComEnv([
            'APP_ENV' => 'production',
            'CACHE_STORE' => 'array',
        ]);

        $container->get(KeyValueStoreInterface::class);
    }

    public function testNaoConfiaEmForwardedProtoSemProxyConfiavel(): void
    {
        $response = $this->call('POST', '/api/auth/login', [
            'email' => 'admin@parrot.com',
            'senha' => 'admin123'
        ], [
            'X-Forwarded-Proto' => 'https',
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Secure', $response->getHeaderLine('Set-Cookie'));
        $this->assertSame('', $response->getHeaderLine('Strict-Transport-Security'));
    }

    public function testConfiaEmForwardedProtoQuandoProxyEstaNaListaConfiavel(): void
    {
        $this->recriarAplicacaoComEnv([
            'TRUSTED_PROXY_IPS' => '10.0.0.1',
        ]);

        $response = $this->call('POST', '/api/auth/login', [
            'email' => 'admin@parrot.com',
            'senha' => 'admin123'
        ], [
            'X-Forwarded-Proto' => 'https',
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Secure', $response->getHeaderLine('Set-Cookie'));
        $this->assertSame('max-age=31536000; includeSubDomains', $response->getHeaderLine('Strict-Transport-Security'));
    }

    public function testBloqueiaEscritaAutenticadaComOrigemExterna(): void
    {
        $token = $this->getJwtToken('admin@parrot.com', 'admin123');

        $response = $this->call('POST', '/api/usuarios', [
            'nome' => 'Tentativa Externa',
            'email' => 'externo@parrot.com',
            'senha' => 'senhaForte123'
        ], [
            'Origin' => 'https://malicioso.exemplo',
        ], $token);

        $this->assertSame(403, $response->getStatusCode());
    }

    private function recriarAplicacaoComEnv(array $sobrescritas): void
    {
        foreach ($sobrescritas as $chave => $valor) {
            $_ENV[$chave] = $valor;
            $_SERVER[$chave] = $valor;
        }

        $containerBuilder = new ContainerBuilder();
        $definitions = require dirname(__DIR__) . '/config/container.php';
        $this->container = $containerBuilder->addDefinitions($definitions)->build();

        $this->app = new Application(
            dirname(__DIR__),
            dirname(__DIR__) . '/config'
        );
        $this->app->setContainer($this->container);
        $this->app->loadRoutes();
        $this->app->loadMiddlewares();

        \App\Middlewares\RateLimitMiddleware::clearStorage();
        TokenRevogado::limparCache();
    }

    private function criarContainerComEnv(array $sobrescritas): \Psr\Container\ContainerInterface
    {
        foreach ($sobrescritas as $chave => $valor) {
            $_ENV[$chave] = $valor;
            $_SERVER[$chave] = $valor;
        }

        $containerBuilder = new ContainerBuilder();
        $definitions = require dirname(__DIR__) . '/config/container.php';

        return $containerBuilder->addDefinitions($definitions)->build();
    }
}
