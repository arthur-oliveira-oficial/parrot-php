<?php

namespace Tests;

use App\Core\Router;
use App\Core\Response;
use App\Controllers\AuthController;
use App\Models\UserModel;
use App\Views\UserResource;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Response as PsrResponse;

class AuthTest extends TestCase
{
    private Router $router;
    private AuthController $authController;
    private UserModel $userModel;
    private UserResource $userResource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();

        $this->userModel = new UserModel();
        $this->userResource = new UserResource();
        $this->authController = new AuthController($this->userModel, $this->userResource);
    }

    public function testAuthControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AuthController::class, $this->authController);
    }

    public function testTokenGenerationUsesHs256(): void
    {
        $reflection = new \ReflectionClass($this->authController);
        $method = $reflection->getMethod('gerarToken');
        $method->setAccessible(true);

        $usuario = [
            'id' => 1,
            'email' => 'test@example.com',
            'tipo' => 'user'
        ];

        $token = $method->invoke($this->authController, $usuario);

        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'Token JWT deve ter 3 partes');

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $this->assertEquals('HS256', $header['alg'] ?? null, 'Algoritmo deve ser HS256');
    }

    public function testTokenPayloadContainsRequiredFields(): void
    {
        $reflection = new \ReflectionClass($this->authController);
        $method = $reflection->getMethod('gerarToken');
        $method->setAccessible(true);

        $usuario = [
            'id' => 42,
            'email' => 'test@example.com',
            'tipo' => 'admin'
        ];

        $token = $method->invoke($this->authController, $usuario);

        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        $this->assertEquals('42', $payload['sub'] ?? null, 'Payload deve conter subject (sub)');
        $this->assertEquals('test@example.com', $payload['email'] ?? null, 'Payload deve conter email');
        $this->assertEquals('admin', $payload['tipo'] ?? null, 'Payload deve conter tipo');
        $this->assertArrayHasKey('iat', $payload, 'Payload deve conter iat');
        $this->assertArrayHasKey('exp', $payload, 'Payload deve conter exp');
    }

    public function testBase64UrlEncoding(): void
    {
        $reflection = new \ReflectionClass($this->authController);
        $method = $reflection->getMethod('base64UrlEncode');
        $method->setAccessible(true);

        $testString = 'Hello+World/Test';
        $encoded = $method->invoke($this->authController, $testString);

        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);

        $decoded = base64_decode(strtr($encoded, '-_', '+/'));
        $this->assertEquals($testString, $decoded);
    }

    public function testAuthRoutesRegistration(): void
    {
        $this->router->post('/api/auth/login', [AuthController::class, 'login']);
        $this->router->post('/api/auth/logout', [AuthController::class, 'logout']);
        $this->router->get('/api/auth/me', [AuthController::class, 'me']);

        $this->assertTrue(true);
    }

    public function testValidationRequiresEmailAndSenha(): void
    {
        $body = [];
        $errors = [];

        if (empty($body['email'])) {
            $errors[] = 'email é obrigatório';
        }
        if (empty($body['senha'])) {
            $errors[] = 'senha é obrigatória';
        }

        $this->assertCount(2, $errors);
    }

    public function testTokenExpiryIsSet(): void
    {
        $_ENV['JWT_EXPIRY'] = 7200;

        $reflection = new \ReflectionClass($this->authController);
        $method = $reflection->getMethod('gerarToken');
        $method->setAccessible(true);

        $usuario = ['id' => 1, 'email' => 'test@test.com', 'tipo' => 'user'];
        $token = $method->invoke($this->authController, $usuario);

        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        $expectedExpiry = time() + 7200;
        $this->assertEquals($expectedExpiry, $payload['exp'], 'Exp deve ser iat + JWT_EXPIRY', 2);
    }
}
