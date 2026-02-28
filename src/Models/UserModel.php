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
        'tipo' => 'string',
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
        // Atualizado para Argon2id (Recomendação OWASP)
        $this->attributes['senha'] = password_hash($value, PASSWORD_ARGON2ID);
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

    /**
     * Paginação de usuários sem os deletados
     *
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Registros por página
     * @return array Dados da paginação com metadados
     */
    public function paginateWithoutTrashed(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        // Busca total de registros (sem os deletados)
        $total = (int) self::whereNull('deletado_em')->count();

        // Busca registros da página
        $registros = self::whereNull('deletado_em')
            ->orderBy('id', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get()
            ->toArray();

        return [
            'data' => $registros,
            'meta' => [
                'pagina_atual' => $page,
                'por_pagina' => $perPage,
                'total_registros' => $total,
                'total_paginas' => (int) ceil($total / $perPage),
            ],
        ];
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

        // Hash "falso" pré-calculado com Argon2id para simular o tempo de validação
        // Isso impede que atacantes meçam o tempo de resposta para descobrir emails válidos
        $dummyHash = '$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHRzdHJpbmc$G2yR/Xf3b2T0uQ2qO8tE0/g6oD0';

        if (!$usuario) {
            // Executa a verificação contra o hash falso apenas para gastar o mesmo tempo
            password_verify($senha, $dummyHash);
            return null;
        }

        // Verifica senha com o hash real do banco de dados
        if (!password_verify($senha, $usuario->senha)) {
            return null;
        }

        // Retorna dados do usuário (sem senha por causa de $hidden)
        return $usuario->toArray();
    }
}
