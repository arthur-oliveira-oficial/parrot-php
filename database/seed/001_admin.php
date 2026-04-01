<?php

/**
 * ===========================================
 * Seed: Usuário Administrador
 * ===========================================
 *
 * Cria o usuário administrador inicial.
 * Execute este seed após as migrations.
 *
 * Credenciais de acesso:
 * - Definidas por ADMIN_EMAIL e ADMIN_PASSWORD
 */

$adminNome = $_ENV['ADMIN_NAME'] ?? getenv('ADMIN_NAME') ?: 'Administrador';
$adminEmail = $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: null;
$adminSenha = $_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: null;

if (empty($adminEmail) || empty($adminSenha)) {
    throw new \RuntimeException('ADMIN_EMAIL e ADMIN_PASSWORD devem ser definidos para executar a seed do administrador.');
}

$senhaHash = password_hash($adminSenha, PASSWORD_ARGON2ID);

// Insere o usuário admin
$stmt = $pdo->prepare("
    INSERT INTO usuarios (nome, email, senha, tipo, created_at, updated_at)
    VALUES (?, ?, ?, ?, NOW(), NOW())
");

$stmt->execute([$adminNome, $adminEmail, $senhaHash, 'admin']);

if (php_sapi_name() === 'cli' && getenv('APP_ENV') !== 'testing') {
    echo "Usuário admin criado: {$adminEmail}\n";
}
