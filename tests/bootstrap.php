<?php

declare(strict_types=1);

/**
 * Bootstrap para testes PHPUnit
 *
 * Carrega o autoloader e as variáveis de ambiente
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carrega variáveis de ambiente APENAS se não estiverem carregadas
if (empty($_ENV['APP_ENV'])) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Mescla com variáveis do servidor
$_ENV = array_merge($_ENV, $_SERVER);
