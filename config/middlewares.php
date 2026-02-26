<?php

use App\Middlewares\CorsMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\SecurityHeadersMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;

/**
 * Fila de middlewares globais (PSR-15).
 * Executados na ordem definida, antes do Router.
 */

return [
    // Error Handler - sempre primeiro para capturar todas as exceções
    ErrorHandlerMiddleware::class,

    // Security Headers
    SecurityHeadersMiddleware::class,

    // Rate Limiting - protege contra ataques de força bruta
    RateLimitMiddleware::class,

    // CORS - por último antes do Router
    CorsMiddleware::class,
];
