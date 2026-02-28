<?php

declare(strict_types=1);

/**
 * ======================================================
 * Migration: Criação da Tabela de Tokens Revogados
 * ======================================================
 *
 * Esta migration cria a tabela 'tokens_revogados' para
 * armazenar tokens JWT que foram explicitamente revogados
 * (ex: durante logout antes da expiração natural).
 *
 * Campos:
 * - id: Chave primária autoincremento
 * - jti: JWT ID único (identificador do token)
 * - revogado_em: Data/hora da revogação
 * - expires_at: Data/hora de expiração original do token
 *
 * @see https://laravel.com/docs/migrations Laravel Migrations
 */

namespace App\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

return new class
{
    /**
     * Executa a migration (cria a tabela)
     */
    public function up(): void
    {
        Schema::schema()->create('tokens_revogados', function (Blueprint $table) {
            // Chave primária autoincremento
            $table->id();

            // JWT ID único - identifica o token
            $table->string('jti', 255)->unique();

            // Data/hora da revogação
            $table->dateTime('revogado_em');

            // Data/hora de expiração original do token
            $table->dateTime('expires_at');

            // Índices para otimização de consultas
            $table->index('jti');
            $table->index('expires_at');
        });
    }

    /**
     * Reverte a migration (remove a tabela)
     */
    public function down(): void
    {
        Schema::schema()->dropIfExists('tokens_revogados');
    }
};
