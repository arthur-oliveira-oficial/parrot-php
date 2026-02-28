<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - Token Revogado Model
 *
 * Model para gerenciar tokens JWT revogados (blacklist).
 * Armazena tokens que foram logout antes da expiração natural.
 */

namespace App\Models;

/**
 * Model de Token Revogado
 *
 * Tabela: tokens_revogados
 *
 * Campos do banco:
 * - id: integer (PK)
 * - jti: string (JWT ID único)
 * - revogado_em: datetime
 * - expires_at: datetime (expiração original do token)
 *
 * @package App\Models
 */
class TokenRevogado extends EloquentModel
{
    /** @var string Nome da tabela */
    protected $table = 'tokens_revogados';

    /** @var bool Desabilitar timestamps automáticos (usamos campos customizados) */
    public $timestamps = false;

    /** @var array Campos que podem ser preenchidos em massa */
    protected $fillable = ['jti', 'revogado_em', 'expires_at'];

    /** @var array Tipos de dados para casts */
    protected $casts = [
        'id' => 'integer',
        'revogado_em' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Verifica se um token está revogado
     *
     * @param string $jti JWT ID único
     * @return bool True se está revogado
     */
    public static function estaRevogado(string $jti): bool
    {
        return self::where('jti', $jti)->exists();
    }

    /**
     * Revoga um token
     *
     * @param string $jti JWT ID único
     * @param int $expiryTimestamp Timestamp de expiração do token
     * @return bool True se inserido com sucesso
     */
    public static function revogar(string $jti, int $expiryTimestamp): bool
    {
        try {
            self::create([
                'jti' => $jti,
                'revogado_em' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', $expiryTimestamp),
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove tokens expirados da blacklist
     *
     * @return int Quantidade de registros removidos
     */
    public static function limparExpirados(): int
    {
        return self::where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
    }
}
