<?php

/**
 * Parrot PHP Framework - Middleware Queue
 *
 * Implementa o padrão "Middleware Pipeline" ou "Onion Pattern".
 *
 * Este é um conceito fundamental em frameworks PSR-15:
 * - Cada middleware recebe a requisição
 * - Pode processar algo e passar para o próximo
 * - Ou pode interceptar e retornar uma resposta diretamente
 * - A resposta "retorna" pela mesma pilha, permitindo pós-processamento
 *
 * Exemplo de fluxo:
 * Request → [Middleware1] → [Middleware2] → [Controller]
 * Response ← [Middleware1] ← [Middleware2] ← [Controller]
 *
 * @see https://www.php-fig.org/psr/psr-15/ PSR-15: HTTP Middlewares
 */

namespace App\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Fila de Middlewares (Pipeline)
 *
 * Implementa o padrão "Onion Pattern":
 * - Mantém uma lista de middlewares a executar
 * - Mantém uma referência ao handler final (controller)
 * - Executa cada middleware sequencialmente
 * - Passa o controle para o próximo até chegar no controller
 *
 * @package App\Core
 */
class MiddlewareQueue implements RequestHandlerInterface
{
    /** @var int Índice do middleware atual na fila */
    private int $index = 0;

    /**
     * Construtor
     *
     * @param array $middlewares Array de MiddlewareInterface
     * @param RequestHandlerInterface|null $handler Handler final (geralmente o router/controller)
     */
    public function __construct(
        private readonly array $middlewares,
        private ?RequestHandlerInterface $handler = null
    ) {}

    /**
     * Define o handler final
     *
     * O handler é chamado após todos os middlewares executarem.
     * Geralmente é o Router que dispatcha para o controller.
     *
     * @param RequestHandlerInterface $handler O controller/router
     */
    public function setHandler(RequestHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * Processa a requisição através dos middlewares
     *
     * Implementa PSR-15 RequestHandlerInterface.
     *
     * Fluxo:
     * 1. Verifica se há mais middlewares na fila
     * 2. Se sim: chama o middleware atual e passa $this (a fila) como próximo
     * 3. Se não: chama o handler final (controller)
     *
     * O middleware pode escolher:
     * - Chamar $this->handle($request) para passar para o próximo
     * - Retornar sua própria resposta para interromper a cadeia
     *
     * @param ServerRequestInterface $request Requisição HTTP
     * @return ResponseInterface Resposta HTTP
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Se não há mais middlewares, chama o handler final (controller)
        if (!isset($this->middlewares[$this->index])) {
            if ($this->handler !== null) {
                return $this->handler->handle($request);
            }

            // Erro se nenhum handler foi definido
            return Response::serverError('Nenhum handler disponível');
        }

        // Obtém o middleware atual e incrementa o índice
        $middleware = $this->middlewares[$this->index];
        $this->index++;

        // Executa o middleware, passando esta fila como "próximo"
        // O middleware decide quando chamar $this para passar adiante
        return $middleware->process($request, $this);
    }
}
