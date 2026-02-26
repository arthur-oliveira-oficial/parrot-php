<?php

declare(strict_types=1);

/**
 * Parrot PHP Framework - Error Handler Middleware
 *
 * Middleware de tratamento de erros.
 * Captura todas as exceções e as converte em respostas HTTP JSON.
 *
 * Tipos de erros tratados:
 * - HttpException: Erros HTTP conhecidos (400, 401, 403, 404, etc.)
 * - Throwable: Erros genéricos do PHP (500)
 *
 * Em produção: oculta mensagens de erro detalhadas
 * Em desenvolvimento: exibe mensagens para debug
 *
 * @see https://www.php-fig.org/psr/psr-15/ PSR-15 Middleware
 */

namespace App\Middlewares;

use App\Exceptions\HttpException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Middleware de Tratamento de Erros
 *
 * Funciona como um "catch" global para erros.
 * Deve ser o primeiro middleware na cadeia (externo).
 */
class ErrorHandlerMiddleware implements MiddlewareInterface
{
    /** @var bool Se deve exibir erros detalhados (development) */
    private bool $displayErrors;
    private ?LoggerInterface $logger;

    /**
     * Construtor
     *
     * @param ResponseFactoryInterface $responseFactory Fábrica para criar respostas de erro
     * @param bool $displayErrors Mostrar erros detalhados (use apenas em dev)
     * @param LoggerInterface|null $logger Sistema de logs (opcional)
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        bool $displayErrors = false,
        ?LoggerInterface $logger = null
    ) {
        $this->displayErrors = $displayErrors;
        $this->logger = $logger;
    }

    /**
     * Processa a requisição capturando erros
     *
     * Fluxo:
     * 1. Tenta executar o handler (próximo middleware ou controller)
     * 2. Se HttpException: trata como erro HTTP conhecido
     * 3. Se outra exceção: trata como erro genérico (500)
     *
     * @param ServerRequestInterface $request Requisição HTTP
     * @param RequestHandlerInterface $handler Próximo na cadeia
     * @return ResponseInterface Resposta ou erro formatado
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (\Throwable $e) {
            return $this->handleGenericException($e);
        }
    }

    private function handleHttpException(HttpException $e): ResponseInterface
    {
        $mensagem = $this->displayErrors
            ? $e->getMessage()
            : $this->getDefaultMessage($e->getStatusCode());

        $response = $this->responseFactory->createResponse($e->getStatusCode());

        foreach ($e->getHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'error' => $mensagem,
            'status' => $e->getStatusCode(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    private function handleGenericException(\Throwable $e): ResponseInterface
    {
        if ($this->logger !== null) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $mensagem = $this->displayErrors
            ? $e->getMessage()
            : 'Erro interno do servidor';

        $statusCode = 500;

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            $statusCode = 404;
            $mensagem = $this->displayErrors ? $e->getMessage() : 'Recurso não encontrado';
        }

        $response = $this->responseFactory->createResponse($statusCode);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'error' => $mensagem,
            'status' => $statusCode,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    private function getDefaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Requisição inválida',
            401 => 'Não autorizado',
            403 => 'Acesso proibido',
            404 => 'Recurso não encontrado',
            405 => 'Método não permitido',
            422 => 'Dados inválidos',
            429 => 'Muitas requisições',
            default => 'Erro interno do servidor',
        };
    }
}
