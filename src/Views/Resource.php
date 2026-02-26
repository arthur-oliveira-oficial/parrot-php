<?php

namespace App\Views;

use App\Core\Response;
use Nyholm\Psr7\Response as NyholmResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Classe abstrata base para Resources (formatadores de resposta JSON).
 * Fornece métodos utilitários para padronizar respostas da API.
 */
abstract class Resource
{
    /**
     * Retorna uma coleção de recursos (array de dados).
     */
    public function collection(array $items, string $key = 'data'): ResponseInterface
    {
        return Response::json([
            $key => $items,
            'meta' => [
                'total' => count($items),
            ]
        ]);
    }

    /**
     * Retorna um único recurso.
     */
    public function item(array $item, string $key = 'data'): ResponseInterface
    {
        return Response::json([$key => $item]);
    }

    /**
     * Retorna resposta de não encontrado (404).
     */
    public function notFound(string $message = 'Recurso não encontrado'): ResponseInterface
    {
        return Response::notFound($message);
    }

    /**
     * Retorna resposta de erro de validação (422).
     */
    public function validationError(array $errors): ResponseInterface
    {
        return Response::json([
            'error' => 'Erro de validação',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Retorna resposta de sucesso com mensagem.
     */
    public function success(string $message, int $statusCode = 200): ResponseInterface
    {
        return Response::json(['message' => $message], $statusCode);
    }

    /**
     * Retorna resposta de criação (201).
     */
    public function created(array $data = []): ResponseInterface
    {
        return Response::json([
            'data' => $data,
            'message' => 'Recurso criado com sucesso',
        ], 201);
    }

    /**
     * Retorna resposta de atualização (200).
     */
    public function updated(array $data = []): ResponseInterface
    {
        return Response::json([
            'data' => $data,
            'message' => 'Recurso atualizado com sucesso',
        ], 200);
    }

    /**
     * Retorna resposta de exclusão (200 ou 204).
     */
    public function deleted(string $message = 'Recurso excluído com sucesso'): ResponseInterface
    {
        return Response::json(['message' => $message], 200);
    }

    /**
     * Transforma um item antes de retornar.
     * Para ser sobrescrito nas classes filhas.
     */
    protected function transform(array $item): array
    {
        return $item;
    }

    /**
     * Transforma uma coleção de itens.
     */
    protected function transformCollection(array $items): array
    {
        return array_map(fn($item) => $this->transform($item), $items);
    }
}
