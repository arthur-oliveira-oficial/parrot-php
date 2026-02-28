<?php

declare(strict_types=1);

namespace Tests;

class UserCrudTest extends TestCase
{
    private int $adminUserId = 1;
    private ?int $normalUserId = null;
    private string $normalUserEmail = 'usuario@parrot.com';
    private string $normalUserPassword = 'senha123';

    protected function setUp(): void
    {
        parent::setUp();

        // Criar usuário normal para testes (se não existir)
        $this->criarUsuarioNormalSeNaoExistir();
    }

    private function criarUsuarioNormalSeNaoExistir(): void
    {
        // Tenta fazer login com usuário normal para ver se existe
        $response = $this->call('POST', '/api/auth/login', [
            'email' => $this->normalUserEmail,
            'senha' => $this->normalUserPassword
        ]);

        if ($response->getStatusCode() === 200) {
            // Usuário já existe, pega o ID
            $body = $this->getJsonBody($response);
            $this->normalUserId = (int) $body['data']['id'];
            return;
        }

        // Cria usuário normal via API de criação
        // Primeiro faz login como admin
        $adminToken = $this->getJwtToken('admin@parrot.com', 'admin123');

        // Cria usuário normal
        $response = $this->call('POST', '/api/usuarios', [
            'nome' => 'Usuario Normal',
            'email' => $this->normalUserEmail,
            'senha' => $this->normalUserPassword
        ], [], $adminToken);

        // Armazena o ID do usuário criado
        $body = $this->getJsonBody($response);
        if (isset($body['data']['id'])) {
            $this->normalUserId = (int) $body['data']['id'];
        }
    }

    public function testListarUsuariosAdmin(): void
    {
        // Admin pode listar todos os usuários
        $token = $this->getJwtToken('admin@parrot.com', 'admin123');

        $response = $this->call('GET', '/api/usuarios', [], [], $token);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->getJsonBody($response);
        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
    }

    public function testListarUsuariosNaoAdmin(): void
    {
        // Usuário comum não pode listar todos os usuários
        $token = $this->getJwtToken($this->normalUserEmail, $this->normalUserPassword);

        $response = $this->call('GET', '/api/usuarios', [], [], $token);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExibirUsuarioProprio(): void
    {
        // Usuário normal pode ver seus próprios dados
        $token = $this->getJwtToken($this->normalUserEmail, $this->normalUserPassword);

        $response = $this->call('GET', "/api/usuarios/{$this->normalUserId}", [], [], $token);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->getJsonBody($response);
        $this->assertEquals($this->normalUserEmail, $body['data']['email']);
    }

    public function testExibirOutroUsuario(): void
    {
        // Usuário normal não pode ver dados de outro usuário (IDOR)
        $token = $this->getJwtToken($this->normalUserEmail, $this->normalUserPassword);

        // Tenta acessar o admin (ID 1)
        $response = $this->call('GET', '/api/usuarios/1', [], [], $token);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExibirAdminPorAdmin(): void
    {
        // Admin pode ver qualquer usuário
        $token = $this->getJwtToken('admin@parrot.com', 'admin123');

        $response = $this->call('GET', "/api/usuarios/{$this->normalUserId}", [], [], $token);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCriarUsuario(): void
    {
        $adminToken = $this->getJwtToken('admin@parrot.com', 'admin123');

        // Email único para evitar conflito
        $email = 'novo_' . time() . '@parrot.com';

        $response = $this->call('POST', '/api/usuarios', [
            'nome' => 'Novo Usuario',
            'email' => $email,
            'senha' => 'senhaForte123'
        ], [], $adminToken);

        $this->assertEquals(201, $response->getStatusCode());

        $body = $this->getJsonBody($response);
        $this->assertArrayHasKey('data', $body);
        $this->assertEquals($email, $body['data']['email']);
    }

    public function testCriarUsuarioEmailDuplicado(): void
    {
        $adminToken = $this->getJwtToken('admin@parrot.com', 'admin123');

        // Tenta criar com email que já existe
        $response = $this->call('POST', '/api/usuarios', [
            'nome' => 'Novo Usuario',
            'email' => 'admin@parrot.com',
            'senha' => 'senhaForte123'
        ], [], $adminToken);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testCriarUsuarioSemNome(): void
    {
        $adminToken = $this->getJwtToken('admin@parrot.com', 'admin123');

        $response = $this->call('POST', '/api/usuarios', [
            'email' => 'teste@parrot.com',
            'senha' => 'senha123'
        ], [], $adminToken);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testAtualizarUsuario(): void
    {
        $token = $this->getJwtToken($this->normalUserEmail, $this->normalUserPassword);

        // Atualiza apenas o nome (não precisa de senha atual)
        $response = $this->call('PUT', "/api/usuarios/{$this->normalUserId}", [
            'nome' => 'Nome Atualizado'
        ], [], $token);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->getJsonBody($response);
        $this->assertEquals('Nome Atualizado', $body['data']['nome']);
    }

    public function testAtualizarSemSenhaAtual(): void
    {
        $token = $this->getJwtToken($this->normalUserEmail, $this->normalUserPassword);

        // Tenta alterar email sem fornecer senha atual
        $response = $this->call('PUT', "/api/usuarios/{$this->normalUserId}", [
            'email' => 'novo@email.com'
        ], [], $token);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAtualizarComSenhaAtual(): void
    {
        $token = $this->getJwtToken($this->normalUserEmail, $this->normalUserPassword);

        // Altera email fornecendo senha atual (email único)
        $novoEmail = 'atualizado_' . time() . '@parrot.com';
        $response = $this->call('PUT', "/api/usuarios/{$this->normalUserId}", [
            'email' => $novoEmail,
            'senha_atual' => $this->normalUserPassword
        ], [], $token);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeletarUsuario(): void
    {
        // Primeiro cria um usuário para deletar
        $adminToken = $this->getJwtToken('admin@parrot.com', 'admin123');
        $email = 'para_deletar_' . time() . '@parrot.com';

        $createResponse = $this->call('POST', '/api/usuarios', [
            'nome' => 'Usuario para Deletar',
            'email' => $email,
            'senha' => 'senha123'
        ], [], $adminToken);

        $createBody = $this->getJsonBody($createResponse);
        $userId = $createBody['data']['id'];

        // Agora deleta o usuário
        $response = $this->call('DELETE', "/api/usuarios/{$userId}", [], [], $adminToken);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeletarUsuarioNaoAutorizado(): void
    {
        // Usuário normal não pode deletar outro usuário
        $token = $this->getJwtToken($this->normalUserEmail, $this->normalUserPassword);

        // Tenta deletar o admin (ID 1)
        $response = $this->call('DELETE', '/api/usuarios/1', [], [], $token);

        $this->assertEquals(403, $response->getStatusCode());
    }
}
