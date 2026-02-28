<?php

/**
 * Parrot PHP Framework - Base Controller
 *
 * Classe abstrata base para todos os controllers da aplicação.
 * Fornece métodos helpers para tarefas comuns:
 * - Obter parâmetros da requisição (URL, corpo)
 * - Obter dados do usuário autenticado
 * - Criar respostas HTTP (sucesso, erro, etc.)
 * - Validar dados de entrada
 *
 * Os controllers concretos estendem esta classe e implementam
 * os métodos que correspondem às rotas.
 *
 * @see https://www.php-fig.org/psr/psr-15/ PSR-15: HTTP Request Handlers
 */

namespace App\Controllers;

use App\Core\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller Base
 *
 * Fornece métodos utilitários para todos os controllers.
 * Use como base para criar novos controllers:
 *
 *     class MeuController extends Controller
 *     {
 *         public function index(ServerRequestInterface $request): ResponseInterface
 *         {
 *             $data = ['message' => 'Olá mundo!'];
 *             return $this->success($data);
 *         }
 *     }
 *
 * @package App\Controllers
 */
abstract class Controller
{
    /**
     * Obtém um parâmetro da requisição
     *
     * Parâmetros vem de:
     * - Parâmetros de rota: /api/usuarios/{id} → getParam('id')
     * - Atributos adicionados por middlewares (como user_id do JWT)
     *
     * @param ServerRequestInterface $request A requisição
     * @param string $name Nome do parâmetro
     * @param mixed $default Valor padrão se não existir
     * @return mixed O valor do parâmetro ou o padrão
     */
    protected function getParam(ServerRequestInterface $request, string $name, mixed $default = null): mixed
    {
        return $request->getAttribute($name, $default);
    }

    /**
     * Obtém o ID do usuário autenticado
     *
     * Este método só funciona se o JwtAuthMiddleware estiver ativo.
     * O middleware adiciona o 'user_id' como atributo da requisição
     * após validar o token JWT.
     *
     * @param ServerRequestInterface $request A requisição
     * @return int|null ID do usuário ou null se não autenticado
     * @see JwtAuthMiddleware
     */
    protected function getUserId(ServerRequestInterface $request): ?int
    {
        return $request->getAttribute('user_id');
    }

    /**
     * Obtém os dados do corpo da requisição
     *
     * Suporta tanto JSON quanto dados de formulário.
     * Tenta primeiro JSON, depois form-urlencoded.
     *
     * @param ServerRequestInterface $request A requisição
     * @return array Dados do corpo
     */
    protected function getBody(ServerRequestInterface $request): array
    {
        // Primeiro tenta JSON (Content-Type: application/json)
        $jsonData = $this->getJsonData($request);
        if (!empty($jsonData)) {
            return $jsonData;
        }

        // Depois tenta form-urlencoded (application/x-www-form-urlencoded)
        $body = $request->getParsedBody();

        if (is_array($body)) {
            return $body;
        }

        return [];
    }

    /**
     * Obtém dados JSON do corpo da requisição
     *
     * Necessário porque o PSR-7 não parseia JSON automaticamente
     * no getParsedBody().
     *
     * @param ServerRequestInterface $request A requisição
     * @return array Dados JSON ou array vazio
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
     * Retorna uma resposta de sucesso (HTTP 200)
     *
     * @param mixed $data Dados a serem retornados em JSON
     * @param int $statusCode Código de status (padrão: 200)
     * @return ResponseInterface
     */
    protected function success(mixed $data, int $statusCode = 200): ResponseInterface
    {
        return Response::json($data, $statusCode);
    }

    /**
     * Retorna uma resposta de erro (HTTP 400+)
     *
     * @param string $message Mensagem de erro
     * @param int $statusCode Código de erro (padrão: 400)
     * @return ResponseInterface
     */
    protected function error(string $message, int $statusCode = 400): ResponseInterface
    {
        return Response::error($message, $statusCode);
    }

    /**
     * Retorna erro 404 - Recurso não encontrado
     *
     * @param string $message Mensagem de erro
     * @return ResponseInterface
     */
    protected function notFound(string $message = 'Recurso não encontrado'): ResponseInterface
    {
        return Response::notFound($message);
    }

    /**
     * Retorna erro 401 - Não autorizado
     *
     * @param string $message Mensagem de erro
     * @return ResponseInterface
     */
    protected function unauthorized(string $message = 'Não autorizado'): ResponseInterface
    {
        return Response::unauthorized($message);
    }

    /**
     * Retorna erro 403 - Proibido
     *
     * @param string $message Mensagem de erro
     * @return ResponseInterface
     */
    protected function forbidden(string $message = 'Proibido'): ResponseInterface
    {
        return Response::forbidden($message);
    }

    /**
     * Retorna sucesso 201 - Recurso criado
     *
     * @param mixed $data Dados do recurso criado
     * @return ResponseInterface
     */
    protected function created(mixed $data = null): ResponseInterface
    {
        return Response::created($data);
    }

    /**
     * Retorna resposta 204 - Sem conteúdo
     *
     * Útil para operações DELETE que não precisam retornar dados.
     *
     * @return ResponseInterface
     */
    protected function noContent(): ResponseInterface
    {
        return Response::noContent();
    }

    /**
     * Valida dados de entrada
     *
     * Validador simples com regras comuns:
     * - 'required' - campo obrigatório
     * - 'email' - email válido
     * - 'integer' - número inteiro
     * - 'min:N' - mínimo N caracteres
     * - 'max:N' - máximo N caracteres
     *
     * @param array $data Dados a validar
     * @param array $rules Regras no formato ['campo' => 'regra']
     * @return array Array de erros (vazio se válido)
     *
     * @example
     *     $errors = $this->validate($body, [
     *         'nome' => 'required',
     *         'email' => 'required|email',
     *         'senha' => 'min:6|max:20'
     *     ]);
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && !isset($data[$field])) {
                $errors[$field] = "O campo {$field} é obrigatório";
                continue;
            }

            if ($rule === 'email' && isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "O campo {$field} deve ser um email válido";
            }

            if ($rule === 'integer' && isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_INT)) {
                $errors[$field] = "O campo {$field} deve ser um número inteiro";
            }

            if (str_starts_with($rule, 'min:')) {
                $min = (int) substr($rule, 4);
                if (isset($data[$field]) && strlen($data[$field]) < $min) {
                    $errors[$field] = "O campo {$field} deve ter pelo menos {$min} caracteres";
                }
            }

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
