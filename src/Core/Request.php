<?php

namespace App\Core;

use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

class Request
{
    public static function createFromGlobals(): ServerRequestInterface
    {
        return ServerRequest::fromGlobals();
    }

    public static function getAttribute(
        ServerRequestInterface $request,
        string $name,
        mixed $default = null
    ): mixed {
        return $request->getAttribute($name, $default);
    }

    public static function getParsedBodyArray(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        if (is_array($body)) {
            return $body;
        }

        return [];
    }

    public static function getJsonData(ServerRequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $body = (string) $request->getBody();
            $data = json_decode($body, true);

            return is_array($data) ? $data : [];
        }

        return [];
    }

    public static function getUserIdFromToken(ServerRequestInterface $request): ?int
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        return $request->getAttribute('user_id');
    }
}
