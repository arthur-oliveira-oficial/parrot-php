<?php

use App\Controllers\UserController;
use App\Controllers\AuthController;
use App\Middlewares\JwtAuthMiddleware;

/**
 * Definição de rotas da aplicação.
 *
 * Formato: 'METODO /caminho' => [Controller::class, 'metodo']
 * Com middleware: 'METODO /caminho' => [Controller::class, 'metodo', Middleware::class]
 */

return [
    // Rotas de autenticação (públicas)
    'POST /api/auth/login' => [AuthController::class, 'login'],
    'POST /api/auth/logout' => [AuthController::class, 'logout'],
    'GET /api/auth/me' => [AuthController::class, 'me', JwtAuthMiddleware::class],

    // Rotas de usuários (protegidas)
    'GET /api/usuarios' => [UserController::class, 'index', JwtAuthMiddleware::class],
    'GET /api/usuarios/{id}' => [UserController::class, 'show', JwtAuthMiddleware::class],
    'POST /api/usuarios' => [UserController::class, 'store', JwtAuthMiddleware::class],
    'PUT /api/usuarios/{id}' => [UserController::class, 'update', JwtAuthMiddleware::class],
    'DELETE /api/usuarios/{id}' => [UserController::class, 'destroy', JwtAuthMiddleware::class],
];
