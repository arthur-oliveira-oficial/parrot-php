<?php

namespace App\Views;

use App\Core\Response;
use Nyholm\Psr7\Response as NyholmResponse;
use Psr\Http\Message\ResponseInterface;

abstract class Resource
{
    public function collection(array $items, string $key = 'data'): ResponseInterface
    {
        return Response::json([
            $key => $items,
            'meta' => [
                'total' => count($items),
            ]
        ]);
    }

    public function item(array $item, string $key = 'data'): ResponseInterface
    {
        return Response::json([$key => $item]);
    }

    public function notFound(string $message = 'Recurso não encontrado'): ResponseInterface
    {
        return Response::notFound($message);
    }

    public function validationError(array $errors): ResponseInterface
    {
        return Response::json([
            'error' => 'Erro de validação',
            'errors' => $errors,
        ], 422);
    }

    public function success(string $message, int $statusCode = 200): ResponseInterface
    {
        return Response::json(['message' => $message], $statusCode);
    }

    public function created(array $data = []): ResponseInterface
    {
        return Response::json([
            'data' => $data,
            'message' => 'Recurso criado com sucesso',
        ], 201);
    }

    public function updated(array $data = []): ResponseInterface
    {
        return Response::json([
            'data' => $data,
            'message' => 'Recurso atualizado com sucesso',
        ], 200);
    }

    public function deleted(string $message = 'Recurso excluído com sucesso'): ResponseInterface
    {
        return Response::json(['message' => $message], 200);
    }

    protected function transform(array $item): array
    {
        return $item;
    }

    protected function transformCollection(array $items): array
    {
        return array_map(fn($item) => $this->transform($item), $items);
    }
}
