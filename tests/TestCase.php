<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Application;
use App\Models\TokenRevogado;
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

        $this->restaurarVariaveisDeAmbienteBase();

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

        \App\Middlewares\RateLimitMiddleware::clearStorage();
        TokenRevogado::limparCache();

        $this->resetDatabase();
    }

    protected function restaurarVariaveisDeAmbienteBase(): void
    {
        $variaveis = [
            'APP_ENV',
            'APP_DEBUG',
            'APP_URL',
            'DB_DRIVER',
            'DB_HOST',
            'DB_PORT',
            'DB_NAME',
            'DB_DATABASE',
            'DB_USER',
            'DB_PASSWORD',
            'JWT_SECRET',
            'JWT_EXPIRY',
            'JWT_ISSUER',
            'JWT_AUDIENCE',
            'ADMIN_NAME',
            'ADMIN_EMAIL',
            'ADMIN_PASSWORD',
            'RATE_LIMIT_MAX_REQUESTS',
            'RATE_LIMIT_WINDOW_SECONDS',
            'RATE_LIMIT_LOGIN_MAX_REQUESTS',
            'RATE_LIMIT_LOGIN_WINDOW_SECONDS',
            'CACHE_STORE',
            'CACHE_PREFIX',
            'REDIS_SCHEME',
            'REDIS_HOST',
            'REDIS_PORT',
            'REDIS_DATABASE',
            'REDIS_PASSWORD',
            'REDIS_TIMEOUT',
            'TRUSTED_PROXY_IPS',
            'CORS_ALLOWED_ORIGINS',
        ];

        foreach ($variaveis as $chave) {
            $valor = getenv($chave);

            if ($valor === false) {
                unset($_ENV[$chave], $_SERVER[$chave]);
                continue;
            }

            $_ENV[$chave] = $valor;
            $_SERVER[$chave] = $valor;
        }
    }

    /**
     * Reseta o banco de dados antes de cada teste
     */
    protected function resetDatabase(): void
    {
        $this->ensureTestDatabaseExists();

        $capsule = $this->container->get(\App\Core\DatabaseCapsule::class)->getCapsule();
        $schema = $capsule->schema();

        // Remove tabelas se existirem
        $schema->dropAllTables();

        // Recria tabela de migrations
        $schema->create('migrations', function ($table) {
            $table->increments('id');
            $table->string('migration')->unique();
            $table->timestamp('executed_at')->nullable();
        });

        // Executa todas as migrations
        $migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*.php');
        sort($migrationFiles);

        foreach ($migrationFiles as $migrationFile) {
            $migrationName = basename($migrationFile);
            $migrationClass = require $migrationFile;
            $migrationClass->up();

            $capsule->table('migrations')->insert([
                'migration' => $migrationName,
                'executed_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Executa seeds
        $pdo = $capsule->getConnection()->getPdo();
        $seedFiles = glob(dirname(__DIR__) . '/database/seed/*.php');
        sort($seedFiles);

        foreach ($seedFiles as $seedFile) {
            require $seedFile;
        }
    }

    /**
     * Garante a criação do banco de testes antes de abrir a conexão principal.
     */
    protected function ensureTestDatabaseExists(): void
    {
        $driver = $_ENV['DB_DRIVER'] ?? 'mysql';

        if ($driver !== 'mysql') {
            return;
        }

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = (int) ($_ENV['DB_PORT'] ?? 3306);
        $database = $_ENV['DB_NAME'] ?? $_ENV['DB_DATABASE'] ?? 'parrot_test';
        $user = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        $pdo = new \PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]
        );

        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            str_replace('`', '``', $database)
        ));
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
        $psrUri = $this->psr17Factory->createUri($uri);

        // Cria headers
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';

        if (!in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true) && !isset($headers['Origin']) && !isset($headers['Referer'])) {
            $appUrl = $_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? 'http://localhost';
            $headers['Referer'] = rtrim((string) $appUrl, '/') . '/teste';
        }

        // Trata os ServerParams vindos do array associativo headers (no teste de RateLimit é passado SERVER_PARAMS lá)
        // Isso é um hack porque o array headers nos testes às vezes é usado para injetar params de server como REMOTE_ADDR
        $serverParams = $_SERVER;
        if (isset($headers['REMOTE_ADDR'])) {
            $serverParams['REMOTE_ADDR'] = $headers['REMOTE_ADDR'];
            unset($headers['REMOTE_ADDR']);
        }

        // Cria request PSR-7 (ServerRequest do Nyholm permite serverParams via factory do pacote, mas ServerRequestCreator também permite)
        $serverRequest = new \Nyholm\Psr7\ServerRequest($method, $psrUri, $headers, null, '1.1', $serverParams);

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
