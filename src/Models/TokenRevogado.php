<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - Token Revogado Model
 *
 * Model para gerenciar tokens JWT revogados (blacklist).
 * Armazena tokens que foram logout antes da expiração natural.
 */

namespace App\Models;

use App\Cache\ArrayKeyValueStore;
use App\Cache\KeyValueStoreInterface;

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
    private static ?KeyValueStoreInterface $cacheStore = null;

    /** @var array<string, bool> Cache local por processo para JTIs já revogados */
    private static array $cacheRevogados = [];

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
        if (isset(self::$cacheRevogados[$jti])) {
            return self::$cacheRevogados[$jti];
        }

        $cacheKey = self::getCacheKey($jti);
        $cacheHit = self::cacheStore()->get($cacheKey);
        if ($cacheHit === true) {
            self::$cacheRevogados[$jti] = true;

            return true;
        }

        $registro = self::query()
            ->where('jti', $jti)
            ->first(['expires_at']);

        $revogado = $registro !== null;

        if ($revogado) {
            self::$cacheRevogados[$jti] = true;

            $ttl = self::ttlFromDatabaseValue($registro->expires_at ?? null);
            self::cacheStore()->set($cacheKey, true, $ttl);
        }

        return $revogado;
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
            self::updateOrCreate([
                'jti' => $jti,
            ], [
                'jti' => $jti,
                'revogado_em' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', $expiryTimestamp),
            ]);

            self::$cacheRevogados[$jti] = true;
            $ttl = max(1, $expiryTimestamp - time());
            self::cacheStore()->set(self::getCacheKey($jti), true, $ttl);

            return true;
        } catch (\Throwable) {
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

    public static function limparCache(): void
    {
        self::$cacheRevogados = [];
        self::cacheStore()->clear();
    }

    public static function definirCacheStore(KeyValueStoreInterface $cacheStore): void
    {
        self::$cacheStore = $cacheStore;
    }

    private static function getCacheKey(string $jti): string
    {
        return 'token_revogado_' . $jti;
    }

    private static function cacheStore(): KeyValueStoreInterface
    {
        if (self::$cacheStore === null) {
            self::$cacheStore = new ArrayKeyValueStore();
        }

        return self::$cacheStore;
    }

    private static function ttlFromDatabaseValue(mixed $expiresAt): int
    {
        if ($expiresAt instanceof \DateTimeInterface) {
            return max(1, $expiresAt->getTimestamp() - time());
        }

        if (is_string($expiresAt)) {
            $timestamp = strtotime($expiresAt);
            if ($timestamp !== false) {
                return max(1, $timestamp - time());
            }
        }

        return 60;
    }
}
