<?php

declare(strict_types=1);

namespace App\Core;

final class JwtService
{
    public function __construct(
        private readonly string $segredo,
        private readonly int $expiracaoEmSegundos,
        private readonly string $emissor,
        private readonly string $audiencia
    ) {
        if ($this->segredo === '') {
            throw new \RuntimeException('JWT_SECRET não configurado. Defina a variável JWT_SECRET no arquivo .env');
        }
    }

    public function gerarToken(array $usuario): string
    {
        $emitidoEm = time();

        $cabecalho = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $payload = [
            'sub' => (string) ($usuario['id'] ?? ''),
            'email' => (string) ($usuario['email'] ?? ''),
            'tipo' => (string) ($usuario['tipo'] ?? ''),
            'jti' => $this->gerarUuid(),
            'iss' => $this->emissor,
            'aud' => $this->audiencia,
            'iat' => $emitidoEm,
            'nbf' => $emitidoEm,
            'exp' => $emitidoEm + $this->expiracaoEmSegundos,
        ];

        $cabecalhoCodificado = $this->base64UrlEncode(json_encode($cabecalho, JSON_THROW_ON_ERROR));
        $payloadCodificado = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $assinatura = $this->base64UrlEncode(
            hash_hmac('sha256', "{$cabecalhoCodificado}.{$payloadCodificado}", $this->segredo, true)
        );

        return "{$cabecalhoCodificado}.{$payloadCodificado}.{$assinatura}";
    }

    public function validarToken(string $token): ?array
    {
        $partes = explode('.', $token);

        if (count($partes) !== 3) {
            return null;
        }

        [$cabecalhoCodificado, $payloadCodificado, $assinatura] = $partes;

        $assinaturaEsperada = $this->base64UrlEncode(
            hash_hmac('sha256', "{$cabecalhoCodificado}.{$payloadCodificado}", $this->segredo, true)
        );

        if (!hash_equals($assinaturaEsperada, $assinatura)) {
            return null;
        }

        try {
            $cabecalho = json_decode($this->base64UrlDecode($cabecalhoCodificado), true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode($this->base64UrlDecode($payloadCodificado), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($cabecalho) || !is_array($payload)) {
            return null;
        }

        if (($cabecalho['typ'] ?? null) !== 'JWT' || ($cabecalho['alg'] ?? null) !== 'HS256') {
            return null;
        }

        if (($payload['iss'] ?? null) !== $this->emissor) {
            return null;
        }

        if (($payload['aud'] ?? null) !== $this->audiencia) {
            return null;
        }

        if (!isset($payload['sub']) || trim((string) $payload['sub']) === '') {
            return null;
        }

        $agora = time();

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $agora) {
            return null;
        }

        if (isset($payload['iat']) && (int) $payload['iat'] > $agora + 5) {
            return null;
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < $agora) {
            return null;
        }

        return $payload;
    }

    public function getExpiracaoEmSegundos(): int
    {
        return $this->expiracaoEmSegundos;
    }

    private function gerarUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }

    private function base64UrlEncode(string $dados): string
    {
        return rtrim(strtr(base64_encode($dados), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $dados): string
    {
        $resto = strlen($dados) % 4;

        if ($resto !== 0) {
            $dados .= str_repeat('=', 4 - $resto);
        }

        $decodificado = base64_decode(strtr($dados, '-_', '+/'), true);

        return $decodificado !== false ? $decodificado : '';
    }
}
