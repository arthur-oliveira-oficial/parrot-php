<?php

/**
 * Parrot PHP Framework - Base Resource
 *
 * Resource (ou Transformer) para formatação de respostas JSON.
 * Fornece métodos para padronizar o formato das respostas da API.
 *
 * Padrão de resposta:
 * - collection: {data: [...], meta: {total: N}}
 * - item: {data: {...}}
 * - errors: {error: "...", errors: {...}}
 *
 * Este é o padrão "API Resource"类似的 ao Laravel API Resources.
 *
 * @see https://laravel.com/docs/eloquent-resources Laravel API Resources
 */

namespace App\Views;

use App\Core\Response;
use Nyholm\Psr7\Response as NyholmResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Resource Base
 *
 * Fornece métodos para formatar respostas JSON padronizadas.
 * Cada subclasse pode sobrescrever transform() para customize a saída.
 */
abstract class Resource
{
    /**
     * Retorna uma coleção de itens
     *
     * Formato: {data: [...], meta: {total: N}}
     *
     * @param array $items Array de itens
     * @param string $key Chave dos dados (padrão: 'data')
     * @return ResponseInterface Resposta JSON
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
     * Retorna um único item
     *
     * Formato: {data: {...}}
     *
     * @param array $item Dados do item
     * @param string $key Chave dos dados (padrão: 'data')
     * @return ResponseInterface Resposta JSON
     */
    public function item(array $item, string $key = 'data'): ResponseInterface
    {
        return Response::json([$key => $item]);
    }

    /**
     * Retorna erro 404
     *
     * @param string $message Mensagem de erro
     * @return ResponseInterface Resposta 404
     */
    public function notFound(string $message = 'Recurso não encontrado'): ResponseInterface
    {
        return Response::notFound($message);
    }

    /**
     * Retorna erro de validação (HTTP 422)
     *
     * @param array $errors Array de erros de validação
     * @return ResponseInterface Resposta 422
     */
    public function validationError(array $errors): ResponseInterface
    {
        return Response::json([
            'error' => 'Erro de validação',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Retorna mensagem de sucesso
     *
     * @param string $message Mensagem
     * @param int $statusCode Código de status
     * @return ResponseInterface Resposta JSON
     */
    public function success(string $message, int $statusCode = 200): ResponseInterface
    {
        return Response::json(['message' => $message], $statusCode);
    }

    /**
     * Retorna sucesso de criação (HTTP 201)
     *
     * @param array $data Dados do recurso criado
     * @return ResponseInterface Resposta 201
     */
    public function created(array $data = []): ResponseInterface
    {
        return Response::json([
            'data' => $data,
            'message' => 'Recurso criado com sucesso',
        ], 201);
    }

    /**
     * Retorna sucesso de atualização (HTTP 200)
     *
     * @param array $data Dados atualizados
     * @return ResponseInterface Resposta 200
     */
    public function updated(array $data = []): ResponseInterface
    {
        return Response::json([
            'data' => $data,
            'message' => 'Recurso atualizado com sucesso',
        ], 200);
    }

    /**
     * Retorna sucesso de exclusão (HTTP 200)
     *
     * @param string $message Mensagem de sucesso
     * @return ResponseInterface Resposta 200
     */
    public function deleted(string $message = 'Recurso excluído com sucesso'): ResponseInterface
    {
        return Response::json(['message' => $message], 200);
    }

    /**
     * Transforma um item antes de retornar
     *
     * Sobrescreva em subclasses para customize a saída.
     * Exemplo: remover campos sensíveis, adicionar campos calculados.
     *
     * @param array $item Dados do item
     * @return array Item transformado
     */
    protected function transform(array $item): array
    {
        return $item;
    }

    /**
     * Transforma uma coleção de itens
     *
     * Aplica transform() a cada item.
     *
     * @param array $items Array de itens
     * @return array Itens transformados
     */
    protected function transformCollection(array $items): array
    {
        return array_map(fn($item) => $this->transform($item), $items);
    }
}
