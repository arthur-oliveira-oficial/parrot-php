<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use DI\ContainerBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

abstract class TestCase extends PHPUnitTestCase
{
    protected Application $app;
    protected ContainerInterface $container;
    protected Psr17Factory $psr17Factory;

    protected function setUp(): void
    {
        parent::setUp();

        // Carrega variáveis de ambiente
        $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
        $_ENV = array_merge($_ENV, $_SERVER);

        // Cria o container PHP-DI
        $containerBuilder = new ContainerBuilder();
        $definitions = require dirname(__DIR__) . '/config/container.php';
        $this->container = $containerBuilder->addDefinitions($definitions)->build();

        // Inicializa a aplicação
        $this->app = new Application(
            dirname(__DIR__),
            dirname(__DIR__) . '/config'
        );
        $this->app->setContainer($this->container);
        $this->app->loadRoutes();
        $this->app->loadMiddlewares();

        // Factory PSR-17 para criar requests
        $this->psr17Factory = new Psr17Factory();
    }

    /**
     * Faz uma requisição HTTP para a aplicação
     */
    protected function call(
        string $method,
        string $uri,
        array $data = [],
        array $headers = [],
        ?string $jwtToken = null
    ): ResponseInterface {
        // Cria URI
        $uri = $this->psr17Factory->createUri($uri);

        // Cria headers
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';

        // Cria request PSR-7
        $serverRequest = $this->psr17Factory->createServerRequest($method, $uri);

        // Adiciona headers
        foreach ($headers as $name => $value) {
            $serverRequest = $serverRequest->withHeader($name, $value);
        }

        // Adiciona dados ao body
        if (!empty($data)) {
            $body = json_encode($data);
            $serverRequest = $serverRequest->withBody(
                $this->psr17Factory->createStream($body)
            );
        }

        // Adiciona cookie JWT se fornecido
        if ($jwtToken !== null) {
            $serverRequest = $serverRequest->withCookieParams([
                'token' => $jwtToken
            ]);
        }

        // Processa a requisição
        return $this->app->handle($serverRequest);
    }

    /**
     * Faz login e retorna o token JWT
     */
    protected function getJwtToken(string $email, string $senha): string
    {
        $response = $this->call('POST', '/api/auth/login', [
            'email' => $email,
            'senha' => $senha
        ]);

        return $this->extractTokenFromResponse($response);
    }

    /**
     * Decodifica o corpo da resposta JSON
     */
    protected function getJsonBody(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $body = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
        }

        return $body;
    }

    /**
     * Extrai o token JWT dos headers Set-Cookie
     */
    protected function extractTokenFromResponse(ResponseInterface $response): string
    {
        $setCookieHeaders = $response->getHeader('Set-Cookie');

        if (empty($setCookieHeaders)) {
            throw new \RuntimeException('No Set-Cookie header in response');
        }

        // Pega o primeiro cookie (pode haver múltiplos)
        $cookie = $setCookieHeaders[0];

        preg_match('/token=([^;]+)/', $cookie, $matches);

        if (empty($matches[1])) {
            throw new \RuntimeException('Token not found in cookies');
        }

        return $matches[1];
    }
}
