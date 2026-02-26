<?php

declare(strict_types=1);

namespace App\Views;

use Psr\Http\Message\ResponseInterface;

/**
 * Resource para formatar respostas de usuário.
 * Remove dados sensíveis como senha.
 */
class UserResource extends Resource
{
    /**
     * Transforma um usuário removendo dados sensíveis.
     */
    protected function transform(array $item): array
    {
        // Remove senha da resposta
        unset($item['senha']);

        // Renomeia campos de data para formato mais legível
        if (isset($item['criado_em'])) {
            $item['criado_em'] = $item['criado_em'];
        }

        if (isset($item['atualizado_em'])) {
            $item['atualizado_em'] = $item['atualizado_em'];
        }

        if (isset($item['deletado_em'])) {
            $item['deletado_em'] = $item['deletado_em'];
        }

        return $item;
    }

    /**
     * Retorna um usuário formatado.
     */
    public function toArray(array $item): array
    {
        return $this->transform($item);
    }

    /**
     * Retorna coleção de usuários formatados.
     */
    public function collection(array $items, string $key = 'data'): ResponseInterface
    {
        $transformedItems = array_map(
            fn($item) => $this->transform($item),
            $items
        );

        return parent::collection($transformedItems, $key);
    }

    /**
     * Retorna um único usuário.
     */
    public function item(array $item, string $key = 'data'): ResponseInterface
    {
        return parent::item($this->transform($item), $key);
    }

    /**
     * Retorna resposta de login bem-sucedido.
     */
    public function loginSuccess(array $user, string $token): ResponseInterface
    {
        $userData = $this->transform($user);

        return \App\Core\Response::json([
            'data' => $userData,
            'token' => $token,
            'message' => 'Login realizado com sucesso',
        ]);
    }

    /**
     * Retorna resposta de erro de login.
     */
    public function loginFailed(string $message = 'Credenciais inválidas'): ResponseInterface
    {
        return \App\Core\Response::unauthorized($message);
    }
}
