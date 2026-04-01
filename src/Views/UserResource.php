<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - User Resource
 *
 * Resource específico para formatação de respostas de usuário.
 * Remove dados sensíveis (senha) antes de retornar.
 */

namespace App\Views;

use Psr\Http\Message\ResponseInterface;

/**
 * Resource de Usuário
 *
 * Remove o campo 'senha' de todas as respostas.
 * Mantém timestamps (criado_em, atualizado_em, deletado_em).
 */
class UserResource extends Resource
{
    /**
     * Transforma item removendo dados sensíveis
     *
     * Remove:
     * - senha: NUNCA deve ser retornada na API
     *
     * Mantém:
     * - criado_em, atualizado_em, deletado_em: datas de auditoria
     *
     * @param array $item Dados do usuário
     * @return array Dados seguros para retorno
     */
    protected function transform(array $item): array
    {
        // Remove senha - JAMAIS retorne a senha em respostas API!
        unset($item['senha']);

        // Mantém campos de data (timestamp de auditoria)
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
     * Converte item para array (alias de transform)
     *
     * @param array $item Dados do usuário
     * @return array Dados seguros
     */
    public function toArray(array $item): array
    {
        return $this->transform($item);
    }

    /**
     * Resposta de login bem-sucedido
     *
     * @param array $user Dados do usuário
     * @return ResponseInterface Resposta de login
     */
    public function loginSuccess(array $user): ResponseInterface
    {
        $userData = $this->transform($user);

        return \App\Core\Response::json([
            'data' => $userData,
            'message' => 'Login realizado com sucesso',
        ]);
    }

    /**
     * Resposta de login falho
     *
     * @param string $message Mensagem de erro
     * @return ResponseInterface Resposta 401
     */
    public function loginFailed(string $message = 'Credenciais inválidas'): ResponseInterface
    {
        return \App\Core\Response::unauthorized($message);
    }
}
