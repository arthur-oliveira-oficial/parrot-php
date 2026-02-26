<?php

namespace App\Controllers;

use App\Core\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class Controller
{
    protected function getParam(ServerRequestInterface $request, string $name, mixed $default = null): mixed
    {
        return $request->getAttribute($name, $default);
    }

    protected function getUserId(ServerRequestInterface $request): ?int
    {
        return $request->getAttribute('user_id');
    }

    protected function getBody(ServerRequestInterface $request): array
    {
        $jsonData = $this->getJsonData($request);
        if (!empty($jsonData)) {
            return $jsonData;
        }

        $body = $request->getParsedBody();

        if (is_array($body)) {
            return $body;
        }

        return [];
    }

    protected function getJsonData(ServerRequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $body = (string) $request->getBody();
            $data = json_decode($body, true);

            return is_array($data) ? $data : [];
        }

        return [];
    }

    protected function success(mixed $data, int $statusCode = 200): ResponseInterface
    {
        return Response::json($data, $statusCode);
    }

    protected function error(string $message, int $statusCode = 400): ResponseInterface
    {
        return Response::error($message, $statusCode);
    }

    protected function notFound(string $message = 'Recurso não encontrado'): ResponseInterface
    {
        return Response::notFound($message);
    }

    protected function unauthorized(string $message = 'Não autorizado'): ResponseInterface
    {
        return Response::unauthorized($message);
    }

    protected function created(mixed $data = null): ResponseInterface
    {
        return Response::created($data);
    }

    protected function noContent(): ResponseInterface
    {
        return Response::noContent();
    }

    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && !isset($data[$field])) {
                $errors[$field] = "O campo {$field} é obrigatório";
                continue;
            }

            if ($rule === 'email' && isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "O campo {$field} deve ser um email válido";
            }

            if ($rule === 'integer' && isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_INT)) {
                $errors[$field] = "O campo {$field} deve ser um número inteiro";
            }

            if (str_starts_with($rule, 'min:')) {
                $min = (int) substr($rule, 4);
                if (isset($data[$field]) && strlen($data[$field]) < $min) {
                    $errors[$field] = "O campo {$field} deve ter pelo menos {$min} caracteres";
                }
            }

            if (str_starts_with($rule, 'max:')) {
                $max = (int) substr($rule, 4);
                if (isset($data[$field]) && strlen($data[$field]) > $max) {
                    $errors[$field] = "O campo {$field} deve ter no máximo {$max} caracteres";
                }
            }
        }

        return $errors;
    }
}
