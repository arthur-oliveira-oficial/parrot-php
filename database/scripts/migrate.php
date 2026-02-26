<?php

/**
 * ===========================================
 * Script de Migrations
 * ===========================================
 *
 * Executa migrations de banco de dados.
 *
 * Uso:
 *   php database/scripts/migrate.php        - Executa migrations pendentes
 *   php database/scripts/migrate.php rollback - Reverte última migration
 *   php database/scripts/migrate.php rollback all - Reverte todas as migrations
 *
 * @see database/migrations/ Arquivo de migrations
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->load();

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;
$capsule->addConnection([
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'database' => $_ENV['DB_NAME'] ?? 'parrot_db',
    'username' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

$schema = $capsule->schema();

if (!$schema->hasTable('migrations')) {
    $schema->create('migrations', function ($table) {
        $table->increments('id');
        $table->string('migration')->unique();
        $table->timestamp('executed_at')->nullable();
    });
    echo "Tabela 'migrations' criada com sucesso.\n";
}

$executedMigrations = $capsule->table('migrations')
    ->orderBy('id', 'desc')
    ->pluck('migration')
    ->toArray();

$allExecuted = $capsule->table('migrations')
    ->orderBy('id', 'asc')
    ->pluck('migration')
    ->toArray();

$migrationFiles = glob(__DIR__ . '/../migrations/*.php');
sort($migrationFiles);

$command = $argv[1] ?? 'migrate';

if ($command === 'rollback') {
    $limit = isset($argv[2]) && $argv[2] === 'all' ? count($executedMigrations) : 1;

    if (empty($executedMigrations)) {
        echo "Nenhuma migration para reverter.\n";
        exit(0);
    }

    $toRollback = array_slice($executedMigrations, 0, $limit);

    foreach ($toRollback as $migrationName) {
        echo "Revertendo: {$migrationName}\n";

        $migrationFile = __DIR__ . '/../migrations/' . $migrationName;

        if (!file_exists($migrationFile)) {
            echo "Arquivo não encontrado: {$migrationFile}\n";
            continue;
        }

        $migrationClass = require $migrationFile;

        if (method_exists($migrationClass, 'down')) {
            $migrationClass->down();
        }

        $capsule->table('migrations')
            ->where('migration', $migrationName)
            ->delete();

        echo "OK\n";
    }

    echo "Rollback executado com sucesso!\n";
    exit(0);
}

$pendingMigrations = array_filter($migrationFiles, function ($file) use ($allExecuted) {
    return !in_array(basename($file), $allExecuted);
});

if (empty($pendingMigrations)) {
    echo "Nenhuma migration pendente. Tudo atualizado.\n";
    exit(0);
}

foreach ($pendingMigrations as $migrationFile) {
    $migrationName = basename($migrationFile);
    echo "Executando: {$migrationName}\n";

    $migrationClass = require $migrationFile;
    $migrationClass->up();

    $capsule->table('migrations')->insert([
        'migration' => $migrationName,
        'executed_at' => date('Y-m-d H:i:s'),
    ]);

    echo "OK\n";
}

echo "Migrations executadas com sucesso!\n";
