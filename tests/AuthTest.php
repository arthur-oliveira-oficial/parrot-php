<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\AuthController;
use App\Core\JwtService;
use App\Views\UserResource;
use Nyholm\Psr7\Factory\Psr17Factory;

class AuthTest extends TestCase
{
    public function testLoginSucesso(): void
    {
        $response = $this->call('POST', '/api/auth/login', [
            'email' => 'admin@parrot.com',
            'senha' => 'admin123'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('token=', $response->getHeaderLine('Set-Cookie'));
        $this->assertStringContainsString('HttpOnly', $response->getHeaderLine('Set-Cookie'));
        $this->assertStringContainsString('SameSite=Strict', $response->getHeaderLine('Set-Cookie'));

        $body = $this->getJsonBody($response);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayNotHasKey('token', $body);
    }

    public function testLoginNormalizaEmail(): void
    {
        $response = $this->call('POST', '/api/auth/login', [
            'email' => '  ADMIN@PARROT.COM  ',
            'senha' => 'admin123'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLoginSenhaIncorreta(): void
    {
        $response = $this->call('POST', '/api/auth/login', [
            'email' => 'admin@parrot.com',
            'senha' => 'senha_errada'
        ]);

        $this->assertEquals(401, $response->getStatusCode());

        $body = $this->getJsonBody($response);
        $this->assertArrayHasKey('error', $body);
    }

    public function testLoginUsuarioNaoExistente(): void
    {
        $response = $this->call('POST', '/api/auth/login', [
            'email' => 'naoexiste@parrot.com',
            'senha' => 'qualquer_senha'
        ]);

        $this->assertEquals(401, $response->getStatusCode());

        $body = $this->getJsonBody($response);
        $this->assertArrayHasKey('error', $body);
    }

    public function testLoginDadosInvalidos(): void
    {
        $response = $this->call('POST', '/api/auth/login', []);

        $this->assertEquals(422, $response->getStatusCode());

        $body = $this->getJsonBody($response);
        $this->assertArrayHasKey('error', $body);
    }

    public function testLoginEmailInvalido(): void
    {
        $response = $this->call('POST', '/api/auth/login', [
            'email' => 'email_invalido',
            'senha' => 'qualquer_senha'
        ]);

        // Sistema retorna 401 para email inválido (trata como credenciais inválidas)
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testLogout(): void
    {
        // Primeiro faz login para obter token
        $token = $this->getJwtToken('admin@parrot.com', 'admin123');

        // Agora faz logout
        $response = $this->call('POST', '/api/auth/logout', [], [], $token);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Max-Age=0', $response->getHeaderLine('Set-Cookie'));

        $body = $this->getJsonBody($response);
        $this->assertArrayHasKey('message', $body);
    }

    public function testLogoutSemAutenticacao(): void
    {
        $response = $this->call('POST', '/api/auth/logout');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testTokenRevogadoERejeitadoAposLogout(): void
    {
        $token = $this->getJwtToken('admin@parrot.com', 'admin123');

        $logoutResponse = $this->call('POST', '/api/auth/logout', [], [], $token);
        $this->assertEquals(200, $logoutResponse->getStatusCode());

        $meResponse = $this->call('GET', '/api/auth/me', [], [], $token);
        $this->assertEquals(401, $meResponse->getStatusCode());
    }

    public function testLogoutFalhaQuandoRevogacaoNaoPodeSerPersistida(): void
    {
        $controller = new class(
            new \App\Models\UserModel(),
            new UserResource(),
            new JwtService('segredo-teste', 3600, 'http://localhost', 'parrot-api')
        ) extends AuthController {
            protected function revogarTokenAtual(array $payload): void
            {
                throw new \RuntimeException('falha simulada');
            }
        };

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('POST', '/api/auth/logout')
            ->withAttribute('jwt_payload', [
                'jti' => 'token-teste',
                'exp' => time() + 3600,
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('falha simulada');

        $controller->logout($request);
    }

    public function testMe(): void
    {
        // Primeiro faz login para obter token
        $token = $this->getJwtToken('admin@parrot.com', 'admin123');

        // Agora chama /me
        $response = $this->call('GET', '/api/auth/me', [], [], $token);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->getJsonBody($response);
        $this->assertArrayHasKey('data', $body);
        $this->assertEquals('admin@parrot.com', $body['data']['email']);
    }

    public function testMeSemAutenticacao(): void
    {
        $response = $this->call('GET', '/api/auth/me');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testRateLimitingPorUsuarioNaMesmaRede(): void
    {
        // Limpar storage de rate limit antes do teste
        \App\Middlewares\RateLimitMiddleware::clearStorage();

        // Configuração de ambiente para simular IP de rede (100.100.100.100)
        $serverParams = ['REMOTE_ADDR' => '100.100.100.100'];

        // Token do Admin (ID: 1)
        $tokenAdmin = $this->getJwtToken('admin@parrot.com', 'admin123');

        // Criar um usuário 2 para o teste
        $this->call('POST', '/api/usuarios', [
            'nome' => 'Usuario Teste 2',
            'email' => 'user2@parrot.com',
            'senha' => 'senhaForte123'
        ], [], $tokenAdmin);

        $tokenUser2 = $this->getJwtToken('user2@parrot.com', 'senhaForte123');

        // Fazer requisições como Admin até quase o limite (phpunit.xml RATE_LIMIT_MAX_REQUESTS é 1000)
        // Vamos fazer o limite configurado localmente. Teste requer injetar a configuração no Container se diferir.
        // Como o token foi buscado em /api/auth/login e usamos o call para o admin user creation, foram gastas
        // algumas requisições nesse processo. Então faremos requisições adicionais mas com uma checagem.
        $limit = 1000;

        $count = 0;
        $blocked = false;
        for ($i = 0; $i < $limit + 10; $i++) {
            $response = $this->call('GET', '/api/auth/me', [], $serverParams, $tokenAdmin);
            if ($response->getStatusCode() === 429) {
                $blocked = true;
                break;
            }
            $this->assertEquals(200, $response->getStatusCode());
            $count++;
        }

        // A requisição acima do limite deve ser bloqueada (429)
        $this->assertTrue($blocked, 'Admin deve ser bloqueado por Rate Limit');

        // AGORA O PULO DO GATO:
        // O Usuario 2 está no mesmo IP ('100.100.100.100').
        // Pelo comportamento antigo, ele já estaria bloqueado.
        // Pelo novo comportamento (Rate Limit por User ID extraído do JWT), ele deve ter o próprio limite e passar.
        $responseUser2 = $this->call('GET', '/api/auth/me', [], $serverParams, $tokenUser2);

        $this->assertEquals(200, $responseUser2->getStatusCode());
    }
}
