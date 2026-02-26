<?php

/**
 * ===========================================
 * Script de Seeds
 * ===========================================
 *
 * Executa seeds para popular o banco de dados com dados iniciais.
 *
 * Uso:
 *   php database/scripts/seed.php
 *
 * Seeds disponíveis em database/seed/
 *
 * @see database/seed/ Arquivos de seed
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

$pdo = $capsule->getConnection()->getPdo();

$seedPath = __DIR__ . '/../seed';

if (!is_dir($seedPath)) {
    echo "Pasta seed não encontrada: {$seedPath}\n";
    exit(0);
}

$seedFiles = glob($seedPath . '/*.php');
sort($seedFiles);

if (empty($seedFiles)) {
    echo "Nenhum arquivo de seed encontrado.\n";
    exit(0);
}

foreach ($seedFiles as $seedFile) {
    $seedName = basename($seedFile);
    echo "Executando seed: {$seedName}\n";

    require_once $seedFile;

    echo "OK\n";
}

echo "Seeds executados com sucesso!\n";
