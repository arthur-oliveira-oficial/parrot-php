<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Exceptions\HttpException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Middleware para tratamento centralizado de erros.
 * Converte exceções em respostas HTTP apropriadas.
 */
class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private bool $displayErrors;
    private ?LoggerInterface $logger;

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        bool $displayErrors = false,
        ?LoggerInterface $logger = null
    ) {
        $this->displayErrors = $displayErrors;
        $this->logger = $logger;
    }

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

        // Adiciona headers
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
        // Log do erro
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

        // Verifica se é uma exceção do Eloquent/DB
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
