<?php

declare(strict_types=1);

namespace App\Views;

use Psr\Http\Message\ResponseInterface;

class UserResource extends Resource
{
    protected function transform(array $item): array
    {
        unset($item['senha']);

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

    public function toArray(array $item): array
    {
        return $this->transform($item);
    }

    public function collection(array $items, string $key = 'data'): ResponseInterface
    {
        $transformedItems = array_map(
            fn($item) => $this->transform($item),
            $items
        );

        return parent::collection($transformedItems, $key);
    }

    public function item(array $item, string $key = 'data'): ResponseInterface
    {
        return parent::item($this->transform($item), $key);
    }

    public function loginSuccess(array $user, string $token): ResponseInterface
    {
        $userData = $this->transform($user);

        return \App\Core\Response::json([
            'data' => $userData,
            'token' => $token,
            'message' => 'Login realizado com sucesso',
        ]);
    }

    public function loginFailed(string $message = 'Credenciais inválidas'): ResponseInterface
    {
        return \App\Core\Response::unauthorized($message);
    }
}
