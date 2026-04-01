# Parrot PHP

Micro-framework REST em PHP 8.4 para APIs JSON, com roteamento via FastRoute, injeção de dependências com PHP-DI, request/response PSR-7, middlewares PSR-15 e persistência principal em Eloquent.

O repositório atual implementa autenticação JWT manual com cookie `HttpOnly`, CRUD de usuários, blacklist persistida de tokens revogados, rate limit, CORS, proteção de CSRF para escritas autenticadas por cookie e testes integrados contra MySQL.

## Visão Geral

- PHP `^8.4`
- `nikic/fast-route` para roteamento
- `php-di/php-di` e `php-di/invoker` para DI e invocação de controllers
- `nyholm/psr7` e `nyholm/psr7-server` para PSR-7
- `illuminate/database` e `illuminate/events` para Eloquent
- `predis/predis` para cache distribuído opcional
- `vlucas/phpdotenv` para ambiente fora de produção
- PHPUnit 11 para testes

Fluxo HTTP atual:

```text
public/index.php
-> App\Core\Application
-> middlewares globais
-> App\Core\FastRouteRouter
-> middleware de rota
-> controller
-> model / resource
-> resposta JSON
```

## O Que Já Existe

- Autenticação:
  - `POST /api/auth/login`
  - `POST /api/auth/logout`
  - `GET /api/auth/me`
- Usuários:
  - `GET /api/usuarios`
  - `GET /api/usuarios/{id}`
  - `POST /api/usuarios`
  - `PUT /api/usuarios/{id}`
  - `DELETE /api/usuarios/{id}`
- Blacklist persistida de JWT em `tokens_revogados`
- Cache abstrato com implementações `Array`, `APCu` e `Redis`
- Seed inicial de administrador
- Migrations e scripts CLI para banco

## Segurança Implementada

- JWT assinado manualmente com `HS256`
- Token aceito exclusivamente do cookie `token`
- Cookie de autenticação com `HttpOnly` e `SameSite=Strict`
- `Secure` habilitado em HTTPS, produção ou `X-Forwarded-Proto=https` vindo de proxy confiável
- Revogação de token em logout com persistência e cache
- Rate limit global por usuário autenticado (`sub` do JWT) ou por IP como fallback
- Rate limit específico no login
- Proteção de CSRF em métodos de escrita quando existe cookie de autenticação
- CORS com lista explícita de origens permitidas
- Headers de segurança com CSP, HSTS, `X-Frame-Options`, `Referrer-Policy` e `Permissions-Policy`
- Senhas com `PASSWORD_ARGON2ID`
- Soft delete de usuários com `deletado_em`
- Respostas JSON padronizadas por `App\Core\Response` e `src/Views`

## Estrutura

```text
parrot-php/
├── config/
│   ├── container.php
│   ├── middlewares.php
│   └── routes.php
├── database/
│   ├── migrations/
│   ├── scripts/
│   └── seed/
├── public/
│   └── index.php
├── src/
│   ├── Cache/
│   ├── Controllers/
│   ├── Core/
│   ├── Exceptions/
│   ├── Middlewares/
│   ├── Models/
│   └── Views/
├── tests/
├── Caddyfile
├── composer.json
├── phpunit.xml
└── README.md
```

## Requisitos

- PHP 8.4 com extensões compatíveis com `pdo_mysql`
- Composer
- MySQL ou MariaDB
- Redis em produção se você quiser subir a aplicação com a configuração segura esperada pelo projeto

Observações importantes:

- O projeto usa Eloquent como camada principal de persistência.
- A suíte de testes não usa SQLite.
- Em `APP_ENV=production`, cache em memória local (`array`, `memory`, `apcu`) é rejeitado pelo container.

## Instalação

```bash
git clone https://github.com/arthur-oliveira-oficial/parrot-php.git
cd parrot-php
composer install
cp .env.example .env
```

Ajuste o `.env` com os valores do seu ambiente.

Variáveis principais:

```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=parrot_db
DB_USER=root
DB_PASSWORD=inserir_password_aqui

JWT_SECRET=alterar_para_uma_chave_secreta_forte_em_producao
JWT_EXPIRY=3600
JWT_ISSUER=http://localhost:8000
JWT_AUDIENCE=parrot-api

ADMIN_NAME=Administrador
ADMIN_EMAIL=admin@parrot.com
ADMIN_PASSWORD=troque_esta_senha_imediatamente

CORS_ALLOWED_ORIGINS=http://localhost:3000
TRUSTED_PROXY_IPS=

RATE_LIMIT_MAX_REQUESTS=60
RATE_LIMIT_WINDOW_SECONDS=60
RATE_LIMIT_LOGIN_MAX_REQUESTS=5
RATE_LIMIT_LOGIN_WINDOW_SECONDS=900

CACHE_STORE=redis
CACHE_PREFIX=parrot:

REDIS_SCHEME=tcp
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DATABASE=0
REDIS_PASSWORD=
REDIS_TIMEOUT=1.5
```

## Banco de Dados

Crie o banco configurado no `.env` e execute:

```bash
php database/scripts/migrate.php
php database/scripts/seed.php
```

O seed inicial cria o administrador a partir de `ADMIN_NAME`, `ADMIN_EMAIL` e `ADMIN_PASSWORD`.

Tabelas atuais:

- `usuarios`
- `tokens_revogados`
- `migrations`

## Executando Localmente

Servidor embutido do PHP:

```bash
php -S localhost:8000 -t public
```

Ou com Caddy/FrankenPHP:

```bash
caddy run
```

O `Caddyfile` atual publica `public/` em `:8080`.

## Middlewares Globais

Ordem real definida em `config/middlewares.php`:

1. `ErrorHandlerMiddleware`
2. `SecurityHeadersMiddleware`
3. `RateLimitMiddleware`
4. `CorsMiddleware`
5. `CsrfGuardMiddleware`

## Autenticação

### Login

`POST /api/auth/login`

Payload:

```json
{
  "email": "admin@parrot.com",
  "senha": "admin123"
}
```

Resposta:

- status `200`
- corpo JSON com dados do usuário autenticado
- cookie `token` no header `Set-Cookie`

### Logout

`POST /api/auth/logout`

Comportamento:

- exige JWT válido via cookie
- revoga o `jti` atual em `tokens_revogados`
- limpa o cookie `token`

### Usuário Atual

`GET /api/auth/me`

Retorna o usuário autenticado a partir do token presente no cookie.

## Controle de Acesso

- `GET /api/usuarios`: apenas `admin`
- `GET /api/usuarios/{id}`: `admin` ou o próprio usuário
- `POST /api/usuarios`: apenas `admin`
- `PUT /api/usuarios/{id}`: `admin` ou o próprio usuário
- `DELETE /api/usuarios/{id}`: `admin` ou o próprio usuário

Regras adicionais atuais:

- alteração de email ou senha exige `senha_atual`
- usuários criados via API entram como `tipo=user`
- email de usuário removido logicamente continua indisponível para reaproveitamento

## Formato de Resposta

Exemplos comuns:

```json
{
  "data": {
    "id": 1,
    "nome": "Administrador",
    "email": "admin@parrot.com",
    "tipo": "admin"
  }
}
```

```json
{
  "error": "Erro de validação",
  "errors": {
    "email": [
      "O campo email é obrigatório."
    ]
  }
}
```

`UserResource` remove `senha` de todas as respostas.

## Testes

Executar tudo:

```bash
./vendor/bin/phpunit
```

Executar um teste específico:

```bash
./vendor/bin/phpunit --filter AuthTest
```

Premissas reais da suíte:

- `APP_ENV=testing`
- `DB_DRIVER=mysql`
- banco esperado: `parrot_test`
- o `tests/TestCase.php` cria o banco se necessário
- as tabelas são recriadas a cada teste

Estado validado localmente nesta atualização:

- `35 tests`
- `1071 assertions`
- execução concluída com sucesso

## Produção

Para rodar em produção com o comportamento esperado do código atual:

- defina `APP_ENV=production`
- defina `APP_DEBUG=false`
- use `JWT_SECRET` forte
- publique atrás de HTTPS
- configure `TRUSTED_PROXY_IPS` se houver proxy reverso
- configure `CACHE_STORE=redis` com Redis disponível

Sem Redis em produção, o container falha por design.

## Observações de Implementação

- O model base ativo do framework é `src/Models/EloquentModel.php`
- o seed do admin e parte da infraestrutura de teste ainda usam PDO diretamente
- o router aceita middlewares por rota via terceiro elemento no array da rota
- em produção, o router usa cache de rotas em `cache/routes.php`

## Licença

MIT.
