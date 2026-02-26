<?php

/**
 * ===========================================
 * Configuração de Rotas do Parrot PHP Framework
 * ===========================================
 *
 * Este arquivo define todas as rotas da API.
 *
 * Formato:
 *   'METODO /caminho' => [Controller::class, 'método']
 *   'METODO /caminho' => [Controller::class, 'método', Middleware::class]
 *
 * Parâmetros dinâmicos:
 *   {id} - captura o valor na URL (ex: /api/usuarios/5)
 *
 * Middlewares:
 *   JwtAuthMiddleware - requer autenticação JWT
 *
 * @see FastRouteRouter
 */

use App\Controllers\UserController;
use App\Controllers\AuthController;
use App\Middlewares\JwtAuthMiddleware;

return [
    // =======================================
    // Rotas de Autenticação
    // =======================================
    // POST /api/auth/login - Login de usuário (público)
    'POST /api/auth/login' => [AuthController::class, 'login'],
    // POST /api/auth/logout - Logout de usuário
    'POST /api/auth/logout' => [AuthController::class, 'logout'],
    // GET /api/auth/me - Dados do usuário atual (requer JWT)
    'GET /api/auth/me' => [AuthController::class, 'me', JwtAuthMiddleware::class],

    // =======================================
    // Rotas de Usuários (CRUD)
    // =======================================
    // GET /api/usuarios - Lista todos os usuários
    'GET /api/usuarios' => [UserController::class, 'index', JwtAuthMiddleware::class],
    // GET /api/usuarios/{id} - Mostra um usuário específico
    'GET /api/usuarios/{id}' => [UserController::class, 'show', JwtAuthMiddleware::class],
    // POST /api/usuarios - Cria novo usuário
    'POST /api/usuarios' => [UserController::class, 'store', JwtAuthMiddleware::class],
    // PUT /api/usuarios/{id} - Atualiza usuário
    'PUT /api/usuarios/{id}' => [UserController::class, 'update', JwtAuthMiddleware::class],
    // DELETE /api/usuarios/{id} - Remove usuário (soft delete)
    'DELETE /api/usuarios/{id}' => [UserController::class, 'destroy', JwtAuthMiddleware::class],
];
