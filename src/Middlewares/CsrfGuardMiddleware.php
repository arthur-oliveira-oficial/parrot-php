<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CsrfGuardMiddleware implements MiddlewareInterface
{
    /** @var array<string, true> */
    private array $origensPermitidas = [];

    public function __construct(array $origensPermitidas = [], ?string $origemAplicacao = null)
    {
        foreach ($origensPermitidas as $origem) {
            if (!is_string($origem)) {
                continue;
            }

            $origem = trim($origem);
            if ($origem !== '') {
                $this->origensPermitidas[$origem] = true;
            }
        }

        if (is_string($origemAplicacao) && $origemAplicacao !== '') {
            $this->origensPermitidas[$origemAplicacao] = true;
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->metodoSeguro($request->getMethod())) {
            return $handler->handle($request);
        }

        $token = $request->getCookieParams()['token'] ?? null;
        if (!is_string($token) || $token === '') {
            return $handler->handle($request);
        }

        $origem = $this->extrairOrigemDaRequisicao($request);
        if ($origem === null) {
            return Response::forbidden('Requisição bloqueada por proteção CSRF. Informe Origin ou Referer válidos.');
        }

        if (!isset($this->origensPermitidas[$origem])) {
            return Response::forbidden('Origem da requisição não autorizada para operações autenticadas.');
        }

        return $handler->handle($request);
    }

    private function metodoSeguro(string $metodo): bool
    {
        return in_array(strtoupper($metodo), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function extrairOrigemDaRequisicao(ServerRequestInterface $request): ?string
    {
        $origin = trim($request->getHeaderLine('Origin'));
        if ($origin !== '') {
            return $origin;
        }

        $referer = trim($request->getHeaderLine('Referer'));
        if ($referer === '') {
            return null;
        }

        $partes = parse_url($referer);
        if (!is_array($partes) || !isset($partes['scheme'], $partes['host'])) {
            return null;
        }

        $origem = $partes['scheme'] . '://' . $partes['host'];

        if (isset($partes['port'])) {
            $origem .= ':' . $partes['port'];
        }

        return $origem;
    }
}
