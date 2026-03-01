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
 * - Email: admin@parrot.com
 * - Senha: admin123
 *
 * AVISO: Altere a senha em produção!
 */

// Gera hash bcrypt da senha
$senhaHash = password_hash('admin123', PASSWORD_BCRYPT);

// Insere o usuário admin
$stmt = $pdo->prepare("
    INSERT INTO usuarios (nome, email, senha, tipo, created_at, updated_at)
    VALUES (?, ?, ?, ?, datetime('now'), datetime('now'))
");

$stmt->execute(['Administrador', 'admin@parrot.com', $senhaHash, 'admin']);

echo "Usuário admin criado: admin@parrot.com / admin123\n";
