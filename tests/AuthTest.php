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
}
