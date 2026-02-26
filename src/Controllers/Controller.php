<?php

namespace App\Controllers;

use App\Core\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Classe abstrata base para todos os Controllers.
 * Fornece métodos utilitários para manipulação de requisições e respostas.
 */
abstract class Controller
{
    /**
     * Obtém um parâmetro da requisição com valor padrão.
     */
    protected function getParam(ServerRequestInterface $request, string $name, mixed $default = null): mixed
    {
        return $request->getAttribute($name, $default);
    }

    /**
     * Obtém o ID do usuário autenticado (a ser implementado com JWT).
     */
    protected function getUserId(ServerRequestInterface $request): ?int
    {
        return $request->getAttribute('user_id');
    }

    /**
     * Obtém o corpo da requisição como array.
     * Suporta JSON e form data automaticamente.
     */
    protected function getBody(ServerRequestInterface $request): array
    {
        // Primeiro tenta obter dados JSON
        $jsonData = $this->getJsonData($request);
        if (!empty($jsonData)) {
            return $jsonData;
        }

        // Depois tenta form data
        $body = $request->getParsedBody();

        if (is_array($body)) {
            return $body;
        }

        return [];
    }

    /**
     * Obtém dados JSON do corpo da requisição.
     */
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

    /**
     * Retorna uma resposta de sucesso.
     */
    protected function success(mixed $data, int $statusCode = 200): ResponseInterface
    {
        return Response::json($data, $statusCode);
    }

    /**
     * Retorna uma resposta de erro.
     */
    protected function error(string $message, int $statusCode = 400): ResponseInterface
    {
        return Response::error($message, $statusCode);
    }

    /**
     * Retorna uma resposta 404.
     */
    protected function notFound(string $message = 'Recurso não encontrado'): ResponseInterface
    {
        return Response::notFound($message);
    }

    /**
     * Retorna uma resposta 401.
     */
    protected function unauthorized(string $message = 'Não autorizado'): ResponseInterface
    {
        return Response::unauthorized($message);
    }

    /**
     * Retorna uma resposta 201 (Created).
     */
    protected function created(mixed $data = null): ResponseInterface
    {
        return Response::created($data);
    }

    /**
     * Retorna uma resposta 204 (No Content).
     */
    protected function noContent(): ResponseInterface
    {
        return Response::noContent();
    }

    /**
     * Valida dados recebidos.
     * Retorna array de erros ou array vazia se válido.
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            // Regra: required
            if ($rule === 'required' && !isset($data[$field])) {
                $errors[$field] = "O campo {$field} é obrigatório";
                continue;
            }

            // Regra: email
            if ($rule === 'email' && isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "O campo {$field} deve ser um email válido";
            }

            // Regra: integer
            if ($rule === 'integer' && isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_INT)) {
                $errors[$field] = "O campo {$field} deve ser um número inteiro";
            }

            // Regra: min:N
            if (str_starts_with($rule, 'min:')) {
                $min = (int) substr($rule, 4);
                if (isset($data[$field]) && strlen($data[$field]) < $min) {
                    $errors[$field] = "O campo {$field} deve ter pelo menos {$min} caracteres";
                }
            }

            // Regra: max:N
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
