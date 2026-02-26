<?php

namespace App\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareQueue implements RequestHandlerInterface
{
    private int $index = 0;

    public function __construct(
        private readonly array $middlewares,
        private ?RequestHandlerInterface $handler = null
    ) {}

    public function setHandler(RequestHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middlewares[$this->index])) {
            if ($this->handler !== null) {
                return $this->handler->handle($request);
            }

            return Response::serverError('Nenhum handler disponível');
        }

        $middleware = $this->middlewares[$this->index];
        $this->index++;

        return $middleware->process($request, $this);
    }
}
