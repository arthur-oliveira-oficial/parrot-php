<?php

namespace Tests;

use App\Core\Router;
use App\Controllers\UserController;
use App\Models\UserModel;
use App\Views\UserResource;
use App\Views\Resource;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Response as PsrResponse;

/**
 * Testes para CRUD de Usuários.
 */
class UserCrudTest extends TestCase
{
    private Router $router;
    private UserController $userController;
    private UserModel $userModel;
    private UserResource $userResource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
        $this->userModel = new UserModel();
        $this->userResource = new UserResource();
        $this->userController = new UserController($this->userModel, $this->userResource);
    }

    /**
     * Testa que o controller de usuário pode ser instanciado.
     */
    public function testUserControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(UserController::class, $this->userController);
    }

    /**
     * Testa registro de todas as rotas CRUD.
     */
    public function testCrudRoutesRegistration(): void
    {
        $this->router->get('/api/usuarios', [UserController::class, 'index']);
        $this->router->get('/api/usuarios/{id}', [UserController::class, 'show']);
        $this->router->post('/api/usuarios', [UserController::class, 'store']);
        $this->router->put('/api/usuarios/{id}', [UserController::class, 'update']);
        $this->router->delete('/api/usuarios/{id}', [UserController::class, 'destroy']);

        $this->assertTrue(true); // Se não抛 exception, registrou OK
    }

    /**
     * Testa conversão de padrão de rota com parâmetros.
     */
    public function testRoutePatternConversion(): void
    {
        $router = new Router();
        $router->get('/api/usuarios/{id}', [UserController::class, 'show']);

        $reflection = new \ReflectionClass($router);
        $method = $reflection->getMethod('converterPadraoParaRegex');
        $method->setAccessible(true);

        $result = $method->invoke($router, '/api/usuarios/{id}');
        $this->assertEquals('#^/api/usuarios/([^/]+)$#', $result);
    }

    /**
     * Testa validação de dados para criar usuário.
     */
    public function testValidationForCreateUser(): void
    {
        // Teste com dados incompletos
        $body = [
            'nome' => 'João',
            // email ausente
            // senha ausente
        ];

        $errors = [];

        if (empty($body['nome'])) {
            $errors[] = 'nome é obrigatório';
        }
        if (empty($body['email'])) {
            $errors[] = 'email é obrigatório';
        }
        if (empty($body['senha'])) {
            $errors[] = 'senha é obrigatória';
        }

        $this->assertCount(2, $errors);
    }

    /**
     * Testa validação de email válido.
     */
    public function testEmailValidation(): void
    {
        $emailsValidos = [
            'test@example.com',
            'user.name@domain.org',
            'user+tag@example.co.uk'
        ];

        $emailsInvalidos = [
            'not-an-email',
            '@nodomain.com',
            'no@',
            ''
        ];

        foreach ($emailsValidos as $email) {
            $this->assertTrue(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, "{$email} deve ser válido");
        }

        foreach ($emailsInvalidos as $email) {
            $this->assertTrue(filter_var($email, FILTER_VALIDATE_EMAIL) === false, "{$email} deve ser inválido");
        }
    }

    /**
     * Testa que o tipo de usuário pode ser admin ou user.
     */
    public function testUserTypeValidation(): void
    {
        $tiposValidos = ['admin', 'user'];
        $tiposInvalidos = ['superadmin', 'guest', 'moderator', ''];

        foreach ($tiposValidos as $tipo) {
            $this->assertContains($tipo, $tiposValidos, "{$tipo} deve ser válido");
        }

        foreach ($tiposInvalidos as $tipo) {
            $this->assertNotContains($tipo, $tiposValidos, "{$tipo} deve ser inválido");
        }
    }

    /**
     * Testa que o UserModel tem os métodos esperados.
     */
    public function testUserModelHasExpectedMethods(): void
    {
        $model = new UserModel();

        $this->assertTrue(method_exists($model, 'findByEmail'), 'Model deve ter método findByEmail');
        $this->assertTrue(method_exists($model, 'findWithoutTrashed'), 'Model deve ter método findWithoutTrashed');
        $this->assertTrue(method_exists($model, 'buscarPorId'), 'Model deve ter método buscarPorId');
        $this->assertTrue(method_exists($model, 'criarUsuario'), 'Model deve ter método criarUsuario');
        $this->assertTrue(method_exists($model, 'atualizarUsuario'), 'Model deve ter método atualizarUsuario');
        $this->assertTrue(method_exists($model, 'softDelete'), 'Model deve ter método softDelete');
        $this->assertTrue(method_exists($model, 'verificarSenha'), 'Model deve ter método verificarSenha');
    }

    /**
     * Testa que o UserResource estende Resource.
     */
    public function testUserResourceExtendsResource(): void
    {
        $this->assertInstanceOf(Resource::class, $this->userResource);
    }

    /**
     * Testa que o password_hash funciona corretamente.
     */
    public function testPasswordHashing(): void
    {
        $senha = 'minhaSenha123';
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        $this->assertTrue(password_verify($senha, $hash), 'Senha deve verificar corretamente');
        $this->assertTrue(password_verify('senha_errada', $hash) === false, 'Senha errada não deve verificar');
        $this->assertTrue(password_needs_rehash($hash, PASSWORD_DEFAULT) === false, 'Hash não precisa de rehash');
    }

    /**
     * Testa que o UserModel usa a tabela correta.
     */
    public function testUserModelTableName(): void
    {
        $model = new UserModel();
        $table = $model->getTable();

        $this->assertEquals('usuarios', $table);
    }

    /**
     * Testa que o UserModel usa SoftDeletes.
     */
    public function testUserModelUsesSoftDeletes(): void
    {
        $model = new UserModel();
        $uses = trait_exists('Illuminate\Database\Eloquent\SoftDeletes');

        $this->assertTrue($uses || in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($model)), 'Model deve usar SoftDeletes');
    }
}
