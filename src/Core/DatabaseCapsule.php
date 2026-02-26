<?php

/**
 * Parrot PHP Framework - Database Capsule
 *
 * Wrapper para Laravel Eloquent Capsule.
 * Fornece acesso ao ORM Eloquent sem precisar do framework Laravel completo.
 *
 * Esta classe configura:
 * - Conexão com banco de dados (MySQL, PostgreSQL, SQLite, etc.)
 * - Global access (permite usar DB::table() diretamente)
 * - Boot do Eloquent (ativa modelos)
 *
 * @see https://laravel.com/docs/eloquent Laravel Eloquent ORM
 * @see https://github.com/illuminate/database Laravel Database Component
 */

namespace App\Core;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Configuração do Banco de Dados
 *
 * Wrapper para Laravel Eloquent Capsule.
 * Permite usar o ORM Eloquent em aplicações PHP que não são Laravel.
 *
 * Configura:
 * - Driver (mysql, pgsql, sqlite)
 * - Host, porta, banco, usuário, senha
 * - Charset e collation
 * - Prefixo de tabelas
 *
 * @package App\Core
 */
class DatabaseCapsule
{
    /** @var Capsule Instância do Laravel Eloquent Capsule */
    private Capsule $capsule;

    /**
     * Construtor - configura e inicializa a conexão com banco
     *
     * @param array $config Configuração do banco (driver, host, name, user, password, etc.)
     *
     * Configuração padrão:
     * - driver: mysql
     * - host: localhost
     * - database: parrot_db
     * - username: root
     * - password: (vazio)
     * - charset: utf8mb4
     * - collation: utf8mb4_unicode_ci
     * - prefix: (vazio)
     * - port: 3306
     */
    public function __construct(array $config)
    {
        // Cria nova instância do Capsule
        $this->capsule = new Capsule();

        // Configura a conexão com o banco de dados
        $this->capsule->addConnection([
            'driver' => $config['driver'] ?? 'mysql',
            'host' => $config['host'] ?? 'localhost',
            'database' => $config['name'] ?? 'parrot_db',
            'username' => $config['user'] ?? 'root',
            'password' => $config['password'] ?? '',
            'charset' => $config['charset'] ?? 'utf8mb4',
            'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
            'prefix' => $config['prefix'] ?? '',
            'port' => $config['port'] ?? 3306,
        ]);

        // Torna a conexão global - permite usar \Illuminate\Database\Capsule\Manager::table()
        $this->capsule->setAsGlobal();

        // Inicia o Eloquent - permite usar Models que estendem Illuminate\Database\Eloquent\Model
        $this->capsule->bootEloquent();
    }

    /**
     * Obtém a instância do Capsule
     *
     * @return Capsule Instância do Capsule para uso avançado
     */
    public function getCapsule(): Capsule
    {
        return $this->capsule;
    }
}
