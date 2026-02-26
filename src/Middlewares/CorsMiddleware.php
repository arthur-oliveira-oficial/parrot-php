<?php

namespace App\Middlewares;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    private readonly array $allowedOrigins;

    private readonly array $allowedMethods;

    private readonly array $allowedHeaders;

    public function __construct(
        array $allowedOrigins = ['http://localhost:3000'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With']
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $origin = $request->getHeaderLine('Origin');

        if (!$this->isOriginAllowed($origin)) {
            $env = getenv('APP_ENV') ?: 'development';

            if ($env === 'development') {
                return $handler->handle($request);
            }

            return new Response(403, [], json_encode(['error' => 'Origin not allowed']));
        }

        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($origin);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', '3600');
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return true;
        }

        $env = getenv('APP_ENV') ?: 'development';
        if ($env === 'development') {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }

    private function handlePreflightRequest(string $origin): ResponseInterface
    {
        return new Response(204, [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => implode(', ', $this->allowedMethods),
            'Access-Control-Allow-Headers' => implode(', ', $this->allowedHeaders),
            'Access-Control-Max-Age' => '3600',
            'Access-Control-Allow-Credentials' => 'true',
        ]);
    }
}
