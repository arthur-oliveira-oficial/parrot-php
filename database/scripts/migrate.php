<?php

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

// Criar tabela de migrations se não existir
if (!$schema->hasTable('migrations')) {
    $schema->create('migrations', function ($table) {
        $table->increments('id');
        $table->string('migration')->unique();
        $table->timestamp('executed_at')->nullable();
    });
    echo "Tabela 'migrations' criada com sucesso.\n";
}

// Obter migrations já executadas (ordem inversa para rollback)
$executedMigrations = $capsule->table('migrations')
    ->orderBy('id', 'desc')
    ->pluck('migration')
    ->toArray();

// Obter todas as migrations do banco em ordem de execução
$allExecuted = $capsule->table('migrations')
    ->orderBy('id', 'asc')
    ->pluck('migration')
    ->toArray();

// Carregar todas as migrations da pasta
$migrationFiles = glob(__DIR__ . '/../migrations/*.php');
sort($migrationFiles);

// Verificar modo de execução
$command = $argv[1] ?? 'migrate';

if ($command === 'rollback') {
    // Rollback da última migration ou todas
    $limit = isset($argv[2]) && $argv[2] === 'all' ? count($executedMigrations) : 1;

    if (empty($executedMigrations)) {
        echo "Nenhuma migration para reverter.\n";
        exit(0);
    }

    $toRollback = array_slice($executedMigrations, 0, $limit);

    foreach ($toRollback as $migrationName) {
        echo "Revertendo: {$migrationName}\n";

        // Encontrar o arquivo da migration
        $migrationFile = __DIR__ . '/../migrations/' . $migrationName;

        if (!file_exists($migrationFile)) {
            echo "Arquivo não encontrado: {$migrationFile}\n";
            continue;
        }

        $migrationClass = require $migrationFile;

        if (method_exists($migrationClass, 'down')) {
            $migrationClass->down();
        }

        // Remover registro da tabela migrations
        $capsule->table('migrations')
            ->where('migration', $migrationName)
            ->delete();

        echo "OK\n";
    }

    echo "Rollback executado com sucesso!\n";
    exit(0);
}

// Modo migrate padrão
// Filtrar apenas migrations pendentes
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

    // Registrar migration executada
    $capsule->table('migrations')->insert([
        'migration' => $migrationName,
        'executed_at' => date('Y-m-d H:i:s'),
    ]);

    echo "OK\n";
}

echo "Migrations executadas com sucesso!\n";
