<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserModel extends EloquentModel
{
    use SoftDeletes;

    const DELETED_AT = 'deletado_em';

    protected $table = 'usuarios';

    protected $fillable = ['nome', 'email', 'senha', 'tipo'];

    protected $hidden = ['senha'];

    protected $casts = [
        'id' => 'integer',
        'tipo' => 'integer',
        'deletado_em' => 'datetime',
    ];

    public function setSenhaAttribute(string $value): void
    {
        $this->attributes['senha'] = password_hash($value, PASSWORD_BCRYPT);
    }

    public function findByEmail(string $email): ?self
    {
        return self::where('email', $email)->first();
    }

    public function findByEmailWithTrashed(string $email): ?self
    {
        return self::withTrashed()->where('email', $email)->first();
    }

    public function allWithTrashed(): array
    {
        return self::withTrashed()->get()->toArray();
    }

    public function allWithoutTrashed(): array
    {
        return self::all()->toArray();
    }

    public function findWithTrashed(int $id): ?array
    {
        try {
            return self::withTrashed()->findOrFail($id)->toArray();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    public function findWithoutTrashed(int $id): ?array
    {
        try {
            return self::findOrFail($id)->toArray();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    public function buscarPorId(int $id): ?array
    {
        try {
            return self::findOrFail($id)->toArray();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    public function criarUsuario(array $data): int
    {
        $usuario = static::query()->create($data);
        return $usuario->id;
    }

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
