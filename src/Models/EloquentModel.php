<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo base que utiliza Eloquent ORM.
 * Estende Illuminate\Database\Eloquent\Model.
 */
abstract class EloquentModel extends Model
{
    /**
     * Desabilita timestamps automáticos se necessário.
     */
    public $timestamps = true;

    /**
     * Os atributos que devem ser ocultos na serialização.
     */
    protected $hidden = ['senha'];

    /**
     * Formato de data para created_at e updated_at.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
