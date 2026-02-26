<?php

use App\Middlewares\CorsMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\SecurityHeadersMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;

return [
    ErrorHandlerMiddleware::class,

    SecurityHeadersMiddleware::class,

    RateLimitMiddleware::class,

    CorsMiddleware::class,
];
