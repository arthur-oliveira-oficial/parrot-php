<?php

declare(strict_types=1);

/**
 * ===========================================
 * Migration: Criação da Tabela de Usuários
 * ===========================================
 *
 * Esta migration cria a tabela 'usuarios' com os campos:
 * - id: Chave primária autoincremento
 * - nome: Nome do usuário (até 255 caracteres)
 * - email: Email único (até 255 caracteres)
 * - senha: Hash bcrypt da senha (até 255 caracteres)
 * - tipo: Enum ('admin' ou 'user'), padrão 'user'
 * - created_at: Data de criação
 * - updated_at: Data de atualização
 * - deletado_em: Soft delete (data de exclusão)
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
        Schema::schema()->create('usuarios', function (Blueprint $table) {
            // Chave primária autoincremento
            $table->id();

            // Nome do usuário
            $table->string('nome', 255);

            // Email único (não pode haver duplicatas)
            $table->string('email', 255)->unique();

            // Senha hash (nunca armazene senhas em texto plano!)
            $table->string('senha', 255);

            // Tipo de usuário: admin ou user
            $table->enum('tipo', ['admin', 'user'])->default('user');

            // Timestamps automáticos (created_at, updated_at)
            $table->timestamps();

            // Soft delete: não remove o registro, marca com data
            // O campo na tabela será 'deletado_em' (traduzido)
            $table->softDeletes('deletado_em');
        });
    }

    /**
     * Reverte a migration (remove a tabela)
     */
    public function down(): void
    {
        Schema::schema()->dropIfExists('usuarios');
    }
};
