<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\UserModel;
use App\Views\UserResource;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller de Autenticação
 * Gerencia login, logout e dados do usuário logado.
 */
class AuthController extends Controller
{
    public function __construct(
        protected UserModel $model,
        protected UserResource $resource
    ) {
    }

    /**
     * POST /api/auth/login
     * Realiza login e retorna token JWT em cookie httpOnly.
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->getBody($request);

        // Validação
        $errors = $this->validate($body, [
            'email' => 'required',
            'senha' => 'required',
        ]);

        if (!empty($errors)) {
            return $this->resource->validationError($errors);
        }

        // Busca usuário
        $usuario = $this->model->verificarSenha($body['email'], $body['senha']);

        if (!$usuario) {
            return $this->resource->loginFailed('Email ou senha inválidos');
        }

        // Gera token JWT
        $token = $this->gerarToken($usuario);

        // Cria resposta com cookie httpOnly
        $response = $this->resource->loginSuccess($usuario, $token);

        // Adiciona cookie httpOnly
        $expiry = time() + (int) ($_ENV['JWT_EXPIRY'] ?? 3600);

        $response = $response->withHeader(
            'Set-Cookie',
            "token={$token}; HttpOnly; Secure; SameSite=Strict; Path=/; Expires=" . gmdate('D, d M Y H:i:s', $expiry) . ' GMT'
        );

        return $response;
    }

    /**
     * POST /api/auth/logout
     * Realiza logout removendo o cookie.
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $response = Response::json(['message' => 'Logout realizado com sucesso']);

        // Remove cookie
        $response = $response->withHeader(
            'Set-Cookie',
            'token=; HttpOnly; Secure; SameSite=Strict; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT'
        );

        return $response;
    }

    /**
     * GET /api/auth/me
     * Retorna dados do usuário logado.
     */
    public function me(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->getUserId($request);

        if (!$userId) {
            return $this->unauthorized('Não autenticado');
        }

        $usuario = $this->model->findWithoutTrashed($userId);

        if (!$usuario) {
            return $this->unauthorized('Usuário não encontrado');
        }

        return $this->resource->item($usuario);
    }

    /**
     * Gera um token JWT.
     */
    private function gerarToken(array $usuario): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'development-secret-change-in-production';
        $expiry = $_ENV['JWT_EXPIRY'] ?? 3600;

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $payload = [
            'sub' => (string) $usuario['id'],
            'email' => $usuario['email'],
            'tipo' => $usuario['tipo'],
            'iat' => time(),
            'exp' => time() + $expiry,
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true)
        );

        return "{$headerEncoded}.{$payloadEncoded}.{$signature}";
    }

    /**
     * Codifica string para base64 URL-safe.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
