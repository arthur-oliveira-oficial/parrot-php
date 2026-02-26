<?php

use App\Controllers\UserController;
use App\Controllers\AuthController;
use App\Middlewares\JwtAuthMiddleware;

return [
    'POST /api/auth/login' => [AuthController::class, 'login'],
    'POST /api/auth/logout' => [AuthController::class, 'logout'],
    'GET /api/auth/me' => [AuthController::class, 'me', JwtAuthMiddleware::class],

    'GET /api/usuarios' => [UserController::class, 'index', JwtAuthMiddleware::class],
    'GET /api/usuarios/{id}' => [UserController::class, 'show', JwtAuthMiddleware::class],
    'POST /api/usuarios' => [UserController::class, 'store', JwtAuthMiddleware::class],
    'PUT /api/usuarios/{id}' => [UserController::class, 'update', JwtAuthMiddleware::class],
    'DELETE /api/usuarios/{id}' => [UserController::class, 'destroy', JwtAuthMiddleware::class],
];
