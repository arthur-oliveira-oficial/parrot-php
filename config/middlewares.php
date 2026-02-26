<?php

/**
 * ===========================================
 * Configuração de Middlewares Globais
 * ===========================================
 *
 * Define a ordem de execução dos middlewares.
 * O primeiro middleware da lista é o primeiro a executar!
 *
 * Ordem de execução (externo → interno):
 * 1. ErrorHandlerMiddleware - Trata erros e exceções
 * 2. SecurityHeadersMiddleware - Adiciona headers de segurança
 * 3. RateLimitMiddleware - Limita requisições por IP
 * 4. CorsMiddleware - Permite requisições cross-origin
 *
 * @see MiddlewareQueue
 * @see Application
 */

use App\Middlewares\CorsMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\SecurityHeadersMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;

/**
 * Middlewares globais da aplicação
 *
 * Executados em TODAS as requisições HTTP.
 */
return [
    // 1. ErrorHandlerMiddleware
    // Captura todas as exceções e retorna JSON de erro
    // DEVE ser o primeiro (mais externo)
    ErrorHandlerMiddleware::class,

    // 2. SecurityHeadersMiddleware
    // Adiciona headers de segurança (X-Content-Type-Options, etc.)
    SecurityHeadersMiddleware::class,

    // 3. RateLimitMiddleware
    // Limita a 60 requisições por minuto por IP
    RateLimitMiddleware::class,

    // 4. CorsMiddleware
    // Permite que frontends em outros domínios acessem a API
    CorsMiddleware::class,
];
