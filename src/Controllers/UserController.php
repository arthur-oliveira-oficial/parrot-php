<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Views\UserResource;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller de Usuário
 * Gerencia endpoints CRUD para usuários.
 */
class UserController extends Controller
{
    public function __construct(
        protected UserModel $model,
        protected UserResource $resource
    ) {
    }

    /**
     * GET /api/usuarios
     * Lista todos os usuários.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $usuarios = $this->model->allWithoutTrashed();

        return $this->resource->collection($usuarios);
    }

    /**
     * GET /api/usuarios/{id}
     * Exibe um usuário específico.
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $this->getParam($request, 'id');

        $usuario = $this->model->findWithoutTrashed($id);

        if (!$usuario) {
            return $this->resource->notFound('Usuário não encontrado');
        }

        return $this->resource->item($usuario);
    }

    /**
     * POST /api/usuarios
     * Cria um novo usuário.
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->getBody($request);

        // Validação
        $errors = $this->validate($body, [
            'nome' => 'required',
            'email' => 'required',
            'senha' => 'required|min:6',
        ]);

        if (!empty($errors)) {
            return $this->resource->validationError($errors);
        }

        // Verifica se email já existe
        $existing = $this->model->findByEmail($body['email']);
        if ($existing) {
            return $this->error('Email já está em uso', 422);
        }

        // Define tipo padrão se não informado
        if (!isset($body['tipo'])) {
            $body['tipo'] = 'user';
        }

        // Valida tipo
        if (!in_array($body['tipo'], ['admin', 'user'])) {
            return $this->error('Tipo inválido. Use: admin ou user', 422);
        }

        $id = $this->model->criarUsuario($body);

        $usuario = $this->model->buscarPorId($id);

        return $this->resource->created($this->resource->toArray($usuario));
    }

    /**
     * PUT /api/usuarios/{id}
     * Atualiza um usuário.
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $this->getParam($request, 'id');
        $body = $this->getBody($request);

        // Verifica se usuário existe
        $usuario = $this->model->findWithoutTrashed($id);
        if (!$usuario) {
            return $this->resource->notFound('Usuário não encontrado');
        }

        // Validação básica
        if (empty($body)) {
            return $this->error('Nenhum dado para atualizar', 422);
        }

        // Verifica se email está sendo alterado e se já existe
        if (isset($body['email']) && $body['email'] !== $usuario['email']) {
            $existing = $this->model->findByEmail($body['email']);
            if ($existing) {
                return $this->error('Email já está em uso', 422);
            }
        }

        // Valida tipo se informado
        if (isset($body['tipo']) && !in_array($body['tipo'], ['admin', 'user'])) {
            return $this->error('Tipo inválido. Use: admin ou user', 422);
        }

        // Remove campos não permitidos
        $allowedFields = ['nome', 'email', 'senha', 'tipo'];
        $data = array_intersect_key($body, array_flip($allowedFields));

        // Não permite atualizar senha como string vazia
        if (isset($data['senha']) && empty($data['senha'])) {
            unset($data['senha']);
        }

        $this->model->atualizarUsuario($id, $data);

        $usuarioAtualizado = $this->model->findWithoutTrashed($id);

        return $this->resource->updated($this->resource->toArray($usuarioAtualizado));
    }

    /**
     * DELETE /api/usuarios/{id}
     * Remove um usuário (soft delete).
     */
    public function destroy(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $this->getParam($request, 'id');

        // Verifica se usuário existe
        $usuario = $this->model->findWithoutTrashed($id);
        if (!$usuario) {
            return $this->resource->notFound('Usuário não encontrado');
        }

        $this->model->softDelete($id);

        return $this->resource->deleted('Usuário removido com sucesso');
    }
}
