<?php

declare(strict_types=1);

namespace Tests;

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

        $body = $this->getJsonBody($response);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('token', $body);
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

        $body = $this->getJsonBody($response);
        $this->assertArrayHasKey('message', $body);
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
            'senha' => 'senha123',
            'senha_confirmacao' => 'senha123'
        ], [], $tokenAdmin);

        $tokenUser2 = $this->getJwtToken('user2@parrot.com', 'senha123');

        // Fazer requisições como Admin até quase o limite (60 é o padrão, vamos fazer 59)
        // Como o RateLimit login tem sua propria configuracao, usamos a rota /me para teste global
        for ($i = 0; $i < 59; $i++) {
            $response = $this->call('GET', '/api/auth/me', [], $serverParams, $tokenAdmin);
            $this->assertEquals(200, $response->getStatusCode());
        }

        // A requisição 60 do Admin deve passar
        $responseAdmin60 = $this->call('GET', '/api/auth/me', [], $serverParams, $tokenAdmin);
        $this->assertEquals(200, $responseAdmin60->getStatusCode());

        // A requisição 61 do Admin deve ser bloqueada (429)
        $responseAdmin61 = $this->call('GET', '/api/auth/me', [], $serverParams, $tokenAdmin);
        $this->assertEquals(429, $responseAdmin61->getStatusCode());

        // AGORA O PULO DO GATO:
        // O Usuario 2 está no mesmo IP ('100.100.100.100').
        // Pelo comportamento antigo, ele já estaria bloqueado.
        // Pelo novo comportamento (Rate Limit por User ID extraído do JWT), ele deve ter o próprio limite e passar.
        $responseUser2 = $this->call('GET', '/api/auth/me', [], $serverParams, $tokenUser2);

        $this->assertEquals(200, $responseUser2->getStatusCode());
    }
}
