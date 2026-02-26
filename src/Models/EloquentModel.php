<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - Eloquent Model Base
 *
 * Model base usando Laravel Eloquent ORM.
 * Fornece funcionalidades avançadas como:
 * - Relacionamentos entre tabelas
 * - Soft Deletes (exclusão lógica)
 * - Timestamps automáticos
 * - Casts de tipos
 * - Scopes
 *
 * Esta classe estende Illuminate\Database\Eloquent\Model
 * e adiciona configurações padrão do projeto.
 *
 * @see https://laravel.com/docs/eloquent Laravel Eloquent ORM
 * @see https://github.com/illuminate/database Laravel Database Component
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model Base Eloquent
 *
 * Configurações padrão:
 * - $timestamps = true: adiciona created_at e updated_at automaticamente
 * - $hidden = ['senha']: oculta campo senha na serialização JSON
 *
 * @package App\Models
 */
abstract class EloquentModel extends Model
{
    /** @var bool Adiciona automaticamente created_at e updated_at */
    public $timestamps = true;

    /** @var array Campos ocultos na serialização JSON (proteção de dados sensíveis) */
    protected $hidden = ['senha'];

    /**
     * Formata datas para JSON
     *
     * Método do Laravel para converter objetos DateTime
     * para string no formato padrão do banco de dados.
     *
     * @param \DateTimeInterface $date Objeto de data
     * @return string Data formatada (Y-m-d H:i:s)
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
