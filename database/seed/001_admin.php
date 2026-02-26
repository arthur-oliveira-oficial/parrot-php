<?php

// Seed: Criar usuário admin
$senhaHash = password_hash('admin123', PASSWORD_BCRYPT);

$stmt = $pdo->prepare("
    INSERT INTO usuarios (nome, email, senha, tipo, created_at, updated_at)
    VALUES (?, ?, ?, ?, NOW(), NOW())
");

$stmt->execute(['Administrador', 'admin@parrot.com', $senhaHash, 'admin']);

echo "Usuário admin criado: admin@parrot.com / admin123\n";
