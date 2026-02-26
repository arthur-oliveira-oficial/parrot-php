<?php

namespace App\Core;

use Nyholm\Psr7\Response as PsrResponse;
use Psr\Http\Message\ResponseInterface;

class Response
{
    public static function json(
        mixed $data,
        int $statusCode = 200,
        array $headers = []
    ): ResponseInterface {
        $defaultHeaders = ['Content-Type' => 'application/json'];
        $headers = array_merge($defaultHeaders, $headers);

        return new PsrResponse(
            $statusCode,
            $headers,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public static function error(
        string $message,
        int $statusCode = 400,
        array $extra = []
    ): ResponseInterface {
        $data = array_merge(['error' => $message], $extra);

        return self::json($data, $statusCode);
    }

    public static function ok(mixed $data): ResponseInterface
    {
        return self::json($data, 200);
    }

    public static function created(mixed $data = null): ResponseInterface
    {
        return self::json($data ?? ['message' => 'Created'], 201);
    }

    public static function notFound(string $message = 'Not found'): ResponseInterface
    {
        return self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): ResponseInterface
    {
        return self::error($message, 403);
    }

    public static function serverError(string $message = 'Internal Server Error'): ResponseInterface
    {
        return self::error($message, 500);
    }

    public static function tooManyRequests(string $message = 'Too Many Requests', int $retryAfter = 60): ResponseInterface
    {
        return self::error($message, 429, ['retry_after' => $retryAfter])
            ->withHeader('Retry-After', (string) $retryAfter);
    }

    public static function noContent(): ResponseInterface
    {
        return new PsrResponse(204);
    }
}
