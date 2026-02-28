<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - User Model
 *
 * Model de usuário usando Laravel Eloquent.
 * Gerencia a tabela 'usuarios' com as seguintes funcionalidades:
 * - Soft Deletes (exclusão lógica)
 * - Campos: id, nome, email, senha, tipo, created_at, updated_at, deletado_em
 * - Tipos de usuário: 'admin' e 'user'
 * - Hash de senha com bcrypt
 *
 * @see EloquentModel
 * @see https://laravel.com/docs/eloquent Laravel Eloquent ORM
 */

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Model de Usuário
 *
 * Tabela: usuarios
 *
 * Campos do banco:
 * - id: integer (PK)
 * - nome: string
 * - email: string (único)
 * - senha: string (hash bcrypt)
 * - tipo: enum ('admin', 'user')
 * - created_at: datetime
 * - updated_at: datetime
 * - deletado_em: datetime (soft delete)
 *
 * @package App\Models
 */
class UserModel extends EloquentModel
{
    /** @var SoftDeletes Trait para exclusão lógica */
    use SoftDeletes;

    /** @var string Nome da coluna de soft delete (tradução do Laravel) */
    const DELETED_AT = 'deletado_em';

    /** @var string Nome da tabela */
    protected $table = 'usuarios';

    /** @var array Campos que podem ser preenchidos em massa (mass assignment) */
    protected $fillable = ['nome', 'email', 'senha'];

    /** @var array Campos protegidos contra mass assignment */
    protected $guarded = ['tipo'];

    /** @var array Campos que não aparecem na serialização JSON */
    protected $hidden = ['senha'];

    /**
     * Tipos de dados para casts automáticos
     *
     * O Laravel converte automaticamente:
     * - id para integer
     * - tipo para integer (enum)
     * - deletado_em para objeto DateTime
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'tipo' => 'integer',
        'deletado_em' => 'datetime',
    ];

    /**
     * Mutator: converte senha em hash antes de salvar
     *
     * Este método é automaticamente chamado pelo Eloquent
     * quando o atributo 'senha' é definido.
     *
     * Usa bcrypt com as opções padrão do PHP.
     *
     * @param string $value Senha em texto plano
     */
    public function setSenhaAttribute(string $value): void
    {
        $this->attributes['senha'] = password_hash($value, PASSWORD_BCRYPT);
    }

    /**
     * Busca usuário por email
     *
     * @param string $email Email do usuário
     * @return self|null Usuário encontrado ou null
     */
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

    /**
     * Cria um novo usuário com privilégios de administrador
     *
     * Método interno seguro para criação de admins.
     * Não exponível via API - uso apenas em código interno.
     *
     * @param string $nome Nome do usuário
     * @param string $email Email do usuário
     * @param string $senha Senha em texto plano
     * @return int ID do usuário criado
     */
    public function criarUsuarioAdmin(string $nome, string $email, string $senha): int
    {
        $usuario = static::query()->create([
            'nome' => $nome,
            'email' => $email,
            'senha' => $senha,
            'tipo' => 'admin',
        ]);
        return $usuario->id;
    }

    /**
     * Cria um novo usuário
     *
     * @param array $data Dados do usuário (nome, email, senha)
     * @return int ID do usuário criado
     */
    public function criarUsuario(array $data): int
    {
        $usuario = static::query()->create($data);
        return $usuario->id;
    }

    /**
     * Atualiza um usuário
     *
     * @param int $id ID do usuário
     * @param array $data Dados a atualizar
     * @return bool True se atualizado, false se não encontrado
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
     * Remove usuário (soft delete)
     *
     * Usa soft delete - o registro não é removido do banco,
     * apenas marcado com deletado_em.
     *
     * @param int $id ID do usuário
     * @return bool True se removido, false se não encontrado
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
     * Verifica credenciais de login
     *
     * @param string $email Email do usuário
     * @param string $senha Senha em texto plano
     * @return array|null Dados do usuário se válido, null se inválido
     */
    public function verificarSenha(string $email, string $senha): ?array
    {
        // Busca usuário pelo email
        $usuario = $this->findByEmail($email);

        if (!$usuario) {
            return null;
        }

        // Verifica senha com bcrypt
        if (!password_verify($senha, $usuario->senha)) {
            return null;
        }

        // Retorna dados do usuário (sem senha por causa de $hidden)
        return $usuario->toArray();
    }
}
