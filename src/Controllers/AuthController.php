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

use App\Core\JwtService;
use App\Core\Response;
use App\Models\UserModel;
use App\Models\TokenRevogado;
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
        protected UserResource $resource,
        private readonly JwtService $jwtService,
        private readonly array $ipsProxyConfiavel = []
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
        $body = $this->normalizarEntradaLogin($this->getBody($request));

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
        $token = $this->jwtService->gerarToken($usuario);

        // Prepara resposta de sucesso
        $response = $this->resource->loginSuccess($usuario);

        // Define cookie HttpOnly com o token
        // HttpOnly: JavaScript não pode acessar (protege contra XSS)
        // Secure: apenas HTTPS em produção
        // SameSite=Strict: previne CSRF
        $response = $response->withHeader(
            'Set-Cookie',
            $this->montarCookieToken($request, $token, $this->jwtService->getExpiracaoEmSegundos())
        );

        return $response;
    }

    /**
     * Logout de usuário
     *
     * Endpoint: POST /api/auth/logout
     *
     * Adiciona o token à blacklist (revogação) e remove o cookie.
     * Isso invalida o token mesmo que alguém tenhacopiado.
     *
     * @param ServerRequestInterface $request Requisição atual
     * @return ResponseInterface Resposta de sucesso
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        // Com a rota protegida por JwtAuthMiddleware, o cookie já foi validado.
        // Ainda assim, só consideramos o cookie HttpOnly para revogação.
        $payload = $request->getAttribute('jwt_payload');

        if (is_array($payload) && isset($payload['jti'], $payload['exp'])) {
            TokenRevogado::revogar((string) $payload['jti'], (int) $payload['exp']);
        }

        // Limpa tokens expirados da blacklist
        TokenRevogado::limparExpirados();

        $response = Response::json(['message' => 'Logout realizado com sucesso']);

        $response = $response->withHeader(
            'Set-Cookie',
            $this->montarCookieToken($request, '', 0)
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

    private function normalizarEntradaLogin(array $dados): array
    {
        if (isset($dados['email']) && is_string($dados['email'])) {
            $dados['email'] = mb_strtolower(trim($dados['email']));
        }

        return $dados;
    }

    private function montarCookieToken(ServerRequestInterface $request, string $token, int $maxAge): string
    {
        $expiraEm = $maxAge > 0 ? time() + $maxAge : 0;
        $partes = [
            'token=' . $token,
            'Path=/',
            'HttpOnly',
            'SameSite=Strict',
        ];

        if ($this->deveUsarCookieSeguro($request)) {
            $partes[] = 'Secure';
        }

        if ($maxAge > 0) {
            $partes[] = 'Max-Age=' . $maxAge;
            $partes[] = 'Expires=' . gmdate('D, d M Y H:i:s', $expiraEm) . ' GMT';
        } else {
            $partes[] = 'Max-Age=0';
            $partes[] = 'Expires=Thu, 01 Jan 1970 00:00:00 GMT';
        }

        return implode('; ', $partes);
    }

    private function deveUsarCookieSeguro(ServerRequestInterface $request): bool
    {
        $schema = strtolower($request->getUri()->getScheme());
        $ambiente = (string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'development');
        $protocoloEncaminhado = strtolower(trim($request->getHeaderLine('X-Forwarded-Proto')));

        if ($schema === 'https' || $ambiente === 'production') {
            return true;
        }

        return $protocoloEncaminhado === 'https' && $this->requisicaoVeioDeProxyConfiavel($request);
    }

    private function requisicaoVeioDeProxyConfiavel(ServerRequestInterface $request): bool
    {
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? null;

        return is_string($remoteAddr) && in_array($remoteAddr, $this->ipsProxyConfiavel, true);
    }
}
