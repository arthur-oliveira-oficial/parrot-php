<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Model de Usuário
 * Gerencia operações de banco de dados para a tabela usuarios.
 */
class UserModel extends EloquentModel
{
    use SoftDeletes;

    /**
     * Define o nome da coluna de soft delete.
     */
    const DELETED_AT = 'deletado_em';

    /**
     * Nome da tabela.
     */
    protected $table = 'usuarios';

    /**
     * Campos que podem ser preenchidos em massa.
     */
    protected $fillable = ['nome', 'email', 'senha', 'tipo'];

    /**
     * Os atributos que devem ser ocultos na serialização.
     */
    protected $hidden = ['senha'];

    /**
     * Os atributos que devem ser convertidos.
     */
    protected $casts = [
        'id' => 'integer',
        'tipo' => 'integer',
        'deletado_em' => 'datetime',
    ];

    /**
     * Hash da senha antes de salvar.
     */
    public function setSenhaAttribute(string $value): void
    {
        $this->attributes['senha'] = password_hash($value, PASSWORD_BCRYPT);
    }

    /**
     * Busca usuário por email.
     */
    public function findByEmail(string $email): ?self
    {
        return self::where('email', $email)->first();
    }

    /**
     * Busca usuário por email (incluindo deletados).
     */
    public function findByEmailWithTrashed(string $email): ?self
    {
        return self::withTrashed()->where('email', $email)->first();
    }

    /**
     * Busca todos os usuários (incluindo deletados).
     */
    public function allWithTrashed(): array
    {
        return self::withTrashed()->get()->toArray();
    }

    /**
     * Busca usuários não deletados.
     */
    public function allWithoutTrashed(): array
    {
        return self::all()->toArray();
    }

    /**
     * Busca um usuário pelo ID (incluindo deletados).
     */
    public function findWithTrashed(int $id): ?array
    {
        try {
            return self::withTrashed()->findOrFail($id)->toArray();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Busca um usuário pelo ID (não deletados).
     */
    public function findWithoutTrashed(int $id): ?array
    {
        try {
            return self::findOrFail($id)->toArray();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Busca um usuário pelo ID.
     * Este wrapper retorna array para compatibilidade com a API.
     */
    public function buscarPorId(int $id): ?array
    {
        try {
            return self::findOrFail($id)->toArray();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Cria um novo usuário usando Eloquent.
     * Usa query()->create() para evitar recursão infinita.
     */
    public function criarUsuario(array $data): int
    {
        $usuario = static::query()->create($data);
        return $usuario->id;
    }

    /**
     * Atualiza um usuário usando Eloquent.
     */
    public function atualizarUsuario(int $id, array $data): bool
    {
        try {
            $usuario = self::findOrFail($id);
            $usuario->update($data);
            return true;
        } catch (ModelNotFoundException) {
            return false;
        }
    }

    /**
     * Soft delete - marca como deletado.
     */
    public function softDelete(int $id): bool
    {
        try {
            $usuario = self::findOrFail($id);
            $usuario->delete();
            return true;
        } catch (ModelNotFoundException) {
            return false;
        }
    }

    /**
     * Verifica se a senha está correta.
     */
    public function verificarSenha(string $email, string $senha): ?array
    {
        $usuario = $this->findByEmail($email);

        if (!$usuario) {
            return null;
        }

        if (!password_verify($senha, $usuario->senha)) {
            return null;
        }

        return $usuario->toArray();
    }
}
