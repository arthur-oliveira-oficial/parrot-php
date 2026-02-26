<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Views\UserResource;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController extends Controller
{
    public function __construct(
        protected UserModel $model,
        protected UserResource $resource
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $usuarios = $this->model->allWithoutTrashed();

        return $this->resource->collection($usuarios);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $this->getParam($request, 'id');

        $usuario = $this->model->findWithoutTrashed($id);

        if (!$usuario) {
            return $this->resource->notFound('Usuário não encontrado');
        }

        return $this->resource->item($usuario);
    }

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
            return $this->error('Email já está em uso', 422);
        }

        if (!isset($body['tipo'])) {
            $body['tipo'] = 'user';
        }

        if (!in_array($body['tipo'], ['admin', 'user'])) {
            return $this->error('Tipo inválido. Use: admin ou user', 422);
        }

        $id = $this->model->criarUsuario($body);

        $usuario = $this->model->buscarPorId($id);

        return $this->resource->created($this->resource->toArray($usuario));
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $this->getParam($request, 'id');
        $body = $this->getBody($request);

        $usuario = $this->model->findWithoutTrashed($id);
        if (!$usuario) {
            return $this->resource->notFound('Usuário não encontrado');
        }

        if (empty($body)) {
            return $this->error('Nenhum dado para atualizar', 422);
        }

        if (isset($body['email']) && $body['email'] !== $usuario['email']) {
            $existing = $this->model->findByEmail($body['email']);
            if ($existing) {
                return $this->error('Email já está em uso', 422);
            }
        }

        if (isset($body['tipo']) && !in_array($body['tipo'], ['admin', 'user'])) {
            return $this->error('Tipo inválido. Use: admin ou user', 422);
        }

        $allowedFields = ['nome', 'email', 'senha', 'tipo'];
        $data = array_intersect_key($body, array_flip($allowedFields));

        if (isset($data['senha']) && empty($data['senha'])) {
            unset($data['senha']);
        }

        $this->model->atualizarUsuario($id, $data);

        $usuarioAtualizado = $this->model->findWithoutTrashed($id);

        return $this->resource->updated($this->resource->toArray($usuarioAtualizado));
    }

    public function destroy(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $this->getParam($request, 'id');

        $usuario = $this->model->findWithoutTrashed($id);
        if (!$usuario) {
            return $this->resource->notFound('Usuário não encontrado');
        }

        $this->model->softDelete($id);

        return $this->resource->deleted('Usuário removido com sucesso');
    }
}
