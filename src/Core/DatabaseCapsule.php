<?php

namespace App\Core;

use Illuminate\Database\Capsule\Manager as Capsule;

class DatabaseCapsule
{
    private Capsule $capsule;

    public function __construct(array $config)
    {
        $this->capsule = new Capsule();

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

        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    public function getCapsule(): Capsule
    {
        return $this->capsule;
    }
}
