<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - Auth Controller
 *
 * Controller responsável por autenticação de usuários.
 * Endpoints disponíveis:
 * - POST /api/auth/login - Login de usuário
 * - POST /api/auth/logout - Logout de usuário
 * - GET /api/auth/me - Dados do usuário atual (requer JWT)
 *
 * O sistema de autenticação usa JWT (JSON Web Token):
 * - Token é gerado no login e enviado via cookie HttpOnly
 * - Middleware JwtAuthMiddleware valida o token em requisições protegidas
 *
 * @see JwtAuthMiddleware
 */

namespace App\Controllers;

use App\Core\Response;
use App\Models\UserModel;
use App\Views\UserResource;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller de Autenticação
 *
 * Gerencia login, logout e recuperação de dados do usuário atual.
 *
 * @package App\Controllers
 */
class AuthController extends Controller
{
    /**
     * Construtor com injeção de dependências
     *
     * O PHP-DI injeta automaticamente o Model e Resource.
     * Isso facilita testes e mantém o código limpo.
     *
     * @param UserModel $model Model para operações de usuário
     * @param UserResource $resource Formatador de respostas
     */
    public function __construct(
        protected UserModel $model,
        protected UserResource $resource
    ) {
    }

    /**
     * Login de usuário
     *
     * Endpoint: POST /api/auth/login
     *
     * Fluxo:
     * 1. Valida email e senha obrigatórios
     * 2. Verifica credenciais no banco
     * 3. Gera token JWT
     * 4. Retorna token em cookie HttpOnly (seguro)
     *
     * @param ServerRequestInterface $request Requisição com email e senha no body
     * @return ResponseInterface Resposta com token JWT ou erro
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        // Obtém dados do corpo da requisição
        $body = $this->getBody($request);

        // Valida campos obrigatórios
        $errors = $this->validate($body, [
            'email' => 'required',
            'senha' => 'required',
        ]);

        if (!empty($errors)) {
            return $this->resource->validationError($errors);
        }

        // Verifica se email e senha são válidos
        $usuario = $this->model->verificarSenha($body['email'], $body['senha']);

        if (!$usuario) {
            return $this->resource->loginFailed('Email ou senha inválidos');
        }

        // Gera token JWT com dados do usuário
        $token = $this->gerarToken($usuario);

        // Prepara resposta de sucesso
        $response = $this->resource->loginSuccess($usuario, $token);

        // Define cookie HttpOnly com o token
        // HttpOnly: JavaScript não pode acessar (protege contra XSS)
        // Secure: apenas HTTPS
        // SameSite=Strict: previne CSRF
        $expiry = time() + (int) ($_ENV['JWT_EXPIRY'] ?? 3600);

        $response = $response->withHeader(
            'Set-Cookie',
            "token={$token}; HttpOnly; Secure; SameSite=Strict; Path=/; Expires=" . gmdate('D, d M Y H:i:s', $expiry) . ' GMT'
        );

        return $response;
    }

    /**
     * Logout de usuário
     *
     * Endpoint: POST /api/auth/logout
     *
     * Simplesmente remove o cookie do token.
     * O token em si não é invalidado (stateless), mas como
     * o cookie é removido, o cliente não o enviando mais.
     *
     * @param ServerRequestInterface $request Requisição atual
     * @return ResponseInterface Resposta de sucesso
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $response = Response::json(['message' => 'Logout realizado com sucesso']);

        $response = $response->withHeader(
            'Set-Cookie',
            'token=; HttpOnly; Secure; SameSite=Strict; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT'
        );

        return $response;
    }

    /**
     * Obtém dados do usuário atual
     *
     * Endpoint: GET /api/auth/me
     * Requer: Middleware JwtAuthMiddleware
     *
     * Este endpoint é protegido por JWT.
     * O middleware valida o token e adiciona 'user_id' na requisição.
     *
     * @param ServerRequestInterface $request Requisição com token JWT
     * @return ResponseInterface Dados do usuário ou erro 401
     */
    public function me(ServerRequestInterface $request): ResponseInterface
    {
        // Obtém ID do usuário (adicionado pelo JwtAuthMiddleware)
        $userId = $this->getUserId($request);

        // Se não tem ID, token é inválido ou expirou
        if (!$userId) {
            return $this->unauthorized('Não autenticado');
        }

        // Busca usuário no banco
        $usuario = $this->model->findWithoutTrashed($userId);

        if (!$usuario) {
            return $this->unauthorized('Usuário não encontrado');
        }

        // Retorna dados do usuário (sem senha)
        return $this->resource->item($usuario);
    }

    /**
     * Gera token JWT
     *
     * JWT (JSON Web Token) é um padrão aberto (RFC 7519) para
     * criar tokens de acesso compactos e auto-contidos.
     *
     * Estrutura do JWT: header.payload.signature
     * - Header: tipo do token e algoritmo (HS256)
     * - Payload: claims (sub=id, email, tipo, iat, exp)
     * - Signature: assinatura com chave secreta
     *
     * @param array $usuario Dados do usuário para incluir no token
     * @return string Token JWT completo
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

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
