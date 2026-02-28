<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - User Controller
 *
 * Controller responsável pelas operações CRUD de usuários.
 * Endpoints disponíveis (todos requerem JWT):
 * - GET /api/usuarios - Lista todos os usuários
 * - GET /api/usuarios/{id} - Mostra um usuário
 * - POST /api/usuarios - Cria novo usuário
 * - PUT /api/usuarios/{id} - Atualiza usuário
 * - DELETE /api/usuarios/{id} - Remove usuário (soft delete)
 *
 * @see JwtAuthMiddleware
 */

namespace App\Controllers;

use App\Models\UserModel;
use App\Views\UserResource;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller de Usuários
 *
 * Implementa CRUD completo de usuários com validação
 * e tratamento de erros.
 *
 * @package App\Controllers
 */
class UserController extends Controller
{
    /**
     * Construtor com injeção de dependências
     *
     * @param UserModel $model Model para operações de banco
     * @param UserResource $resource Formatador de respostas JSON
     */
    public function __construct(
        protected UserModel $model,
        protected UserResource $resource
    ) {
    }

    /**
     * Verifica se o utilizador autenticado tem permissão para aceder ao registo
     *
     * @param ServerRequestInterface $request Requisição HTTP
     * @param int $targetUserId ID do utilizador que se pretende aceder
     * @return bool True se tem permissão, False caso contrário
     */
    private function canAccessUser(ServerRequestInterface $request, int $targetUserId): bool
    {
        $userId = $this->getUserId($request);
        $userTipo = $request->getAttribute('user_tipo');

        // Administradores podem aceder a qualquer utilizador
        if ($userTipo === 'admin') {
            return true;
        }

        // Utilizadores comuns só podem aceder ao próprio perfil
        return $userId === $targetUserId;
    }

    /**
     * Lista todos os usuários
     *
     * Endpoint: GET /api/usuarios
     * Middleware: JwtAuthMiddleware
     *
     * @param ServerRequestInterface $request Requisição HTTP
     * @return ResponseInterface Lista de usuários em JSON
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        // Apenas administradores podem listar todos os utilizadores
        if ($request->getAttribute('user_tipo') !== 'admin') {
            return $this->forbidden('Apenas administradores podem listar todos os utilizadores');
        }

        $usuarios = $this->model->allWithoutTrashed();

        return $this->resource->collection($usuarios);
    }

    /**
     * Exibe um usuário específico
     *
     * Endpoint: GET /api/usuarios/{id}
     *
     * @param ServerRequestInterface $request Requisição com {id} na URL
     * @return ResponseInterface Dados do usuário ou 404
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        // Obtém ID da URL (/usuarios/{id})
        $id = (int) $this->getParam($request, 'id');

        // Verifica autorização (IDOR protection)
        if (!$this->canAccessUser($request, $id)) {
            return $this->forbidden('Não tem permissão para visualizar este utilizador');
        }

        // Busca usuário (excluindo deletados logicamente)
        $usuario = $this->model->findWithoutTrashed($id);

        if (!$usuario) {
            return $this->resource->notFound('Usuário não encontrado');
        }

        return $this->resource->item($usuario);
    }

    /**
     * Cria um novo usuário
     *
     * Endpoint: POST /api/usuarios
     *
     * Validações:
     * - nome: obrigatório
     * - email: obrigatório
     * - senha: obrigatória, mínimo 6 caracteres
     * - tipo: opcional (padrão 'user'), deve ser 'admin' ou 'user'
     *
     * @param ServerRequestInterface $request Requisição com dados no body
     * @return ResponseInterface Usuário criado ou erros de validação
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->getBody($request);

        $errors = $this->validate($body, [
            'nome' => 'required',
            'email' => 'required',
            'senha' => 'required|min:6',
        ]);

        if (!empty($errors)) {
            return $this->resource->validationError($errors);
        }

        $existing = $this->model->findByEmail($body['email']);
        if ($existing) {
            return $this->error('Não foi possível processar o registo com os dados fornecidos.', 422);
        }

        // Usuários criados via API são sempre tipo 'user'
        // Para criar admins, use o método interno criarUsuarioAdmin() do Model

        $id = $this->model->criarUsuario($body);

        $usuario = $this->model->buscarPorId($id);

        return $this->resource->created($this->resource->toArray($usuario));
    }

    /**
     * Atualiza um usuário
     *
     * Endpoint: PUT /api/usuarios/{id}
     *
     * Campos atualizáveis: nome, email, senha, tipo
     * Validações:
     * - Email deve ser único (se alterado)
     * - Tipo deve ser 'admin' ou 'user'
     * - Senha vazia não é atualizada
     *
     * @param ServerRequestInterface $request Requisição com {id} e dados no body
     * @return ResponseInterface Usuário atualizado ou erro
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        // Obtém ID da URL
        $id = (int) $this->getParam($request, 'id');
        // Obtém dados do corpo
        $body = $this->getBody($request);

        // Verifica autorização (IDOR protection)
        if (!$this->canAccessUser($request, $id)) {
            return $this->forbidden('Não tem permissão para atualizar este utilizador');
        }

        // Verifica se usuário existe
        $usuario = $this->model->findWithoutTrashed($id);
        if (!$usuario) {
            return $this->resource->notFound('Usuário não encontrado');
        }

        // Verifica se há dados para atualizar
        if (empty($body)) {
            return $this->error('Nenhum dado para atualizar', 422);
        }

        // Validação de segurança: exigir senha atual para alterar credenciais
        $tentandoAlterarEmail = isset($body['email']) && $body['email'] !== $usuario['email'];
        $tentandoAlterarSenha = !empty($body['senha']);

        if ($tentandoAlterarEmail || $tentandoAlterarSenha) {
            if (empty($body['senha_atual'])) {
                return $this->error('A senha atual é obrigatória para alterar o email ou a senha', 403);
            }

            $senhaValida = $this->model->verificarSenha($usuario['email'], $body['senha_atual']);
            if (!$senhaValida) {
                return $this->error('A senha atual está incorreta', 403);
            }
        }

        // Verifica se email já está em uso (se alterado)
        if (isset($body['email']) && $body['email'] !== $usuario['email']) {
            $existing = $this->model->findByEmail($body['email']);
            if ($existing) {
                return $this->error('Não foi possível processar a atualização com os dados fornecidos.', 422);
            }
        }

        // Filtra apenas campos permitidos (prevenção de campos extras)
        // Nota: 'tipo' foi removido para prevenir escalação de privilégios via Mass Assignment
        $allowedFields = ['nome', 'email', 'senha'];
        $data = array_intersect_key($body, array_flip($allowedFields));

        // Remove senha se vazia (não alterar)
        if (isset($data['senha']) && empty($data['senha'])) {
            unset($data['senha']);
        }

        // Atualiza no banco
        $this->model->atualizarUsuario($id, $data);

        // Retorna usuário atualizado
        $usuarioAtualizado = $this->model->findWithoutTrashed($id);

        return $this->resource->updated($this->resource->toArray($usuarioAtualizado));
    }

    /**
     * Remove um usuário (soft delete)
     *
     * Endpoint: DELETE /api/usuarios/{id}
     *
     * Usa soft delete - o registro não é realmente excluído,
     * apenas marcado como deletado (deleted_at).
     * Isso permite recuperação posterior.
     *
     * @param ServerRequestInterface $request Requisição com {id}
     * @return ResponseInterface Sucesso ou 404
     */
    /**
     * Remove um usuário (soft delete)
     *
     * Endpoint: DELETE /api/usuarios/{id}
     *
     * @param ServerRequestInterface $request Requisição com {id}
     * @return ResponseInterface Sucesso ou 404
     */
    public function destroy(ServerRequestInterface $request): ResponseInterface
    {
        // Obtém ID da URL
        $id = (int) $this->getParam($request, 'id');

        // Verifica autorização (IDOR protection)
        if (!$this->canAccessUser($request, $id)) {
            return $this->forbidden('Não tem permissão para remover este utilizador');
        }

        // Verifica se usuário existe
        $usuario = $this->model->findWithoutTrashed($id);
        if (!$usuario) {
            return $this->resource->notFound('Usuário não encontrado');
        }

        // Soft delete - marca como deletado mas não remove
        $this->model->softDelete($id);

        return $this->resource->deleted('Usuário removido com sucesso');
    }
}
