<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

/**
 * Migration: Criar tabela de usuários
 */
return new class
{
    public function up(): void
    {
        Schema::schema()->create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);
            $table->string('email', 255)->unique();
            $table->string('senha', 255);
            $table->enum('tipo', ['admin', 'user'])->default('user');
            $table->timestamps();
            $table->softDeletes('deletado_em');
        });
    }

    public function down(): void
    {
        Schema::schema()->dropIfExists('usuarios');
    }
};
