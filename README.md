# Parrot PHP

Micro-framework REST em PHP 8.4 para APIs JSON, com FastRoute, PHP-DI, PSR-7/PSR-15, Eloquent, JWT manual via cookie `HttpOnly` e testes integrados contra MySQL.

O README abaixo descreve o que o repositório implementa hoje.

## Stack Atual

- PHP `^8.4`
- `nikic/fast-route`
- `php-di/php-di`
- `php-di/invoker`
- `nyholm/psr7`
- `nyholm/psr7-server`
- `illuminate/database`
- `illuminate/events`
- `predis/predis`
- PHPUnit 11

## Fluxo HTTP

```text
public/index.php
-> container PHP-DI
-> DatabaseCapsule
-> Application
-> middlewares globais
-> FastRouteRouter
-> middleware de rota
-> controller
-> model / resource
-> resposta JSON
```

## O Que Existe no Código

- autenticação manual com JWT assinado em `HS256`
- token entregue e lido exclusivamente do cookie `token`
- logout com blacklist persistida em `tokens_revogados`
- CRUD de usuários com soft delete em `deletado_em`
- rate limit global e rate limit específico de login
- CORS com whitelist explícita
- proteção de CSRF para escritas autenticadas por cookie
- headers de segurança e HSTS quando aplicável
- cache abstrato com `Array`, `APCu` e `Redis`

## Estrutura

```text
config/
database/
docs/
public/
src/
tests/
```

Pastas principais:

- `config/`: container, rotas e middlewares
- `src/Core/`: kernel HTTP, router, JWT, response e banco
- `src/Controllers/`: `AuthController` e `UserController`
- `src/Models/`: `EloquentModel`, `UserModel`, `TokenRevogado`
- `src/Middlewares/`: autenticação, CORS, CSRF, rate limit, erro e headers
- `src/Views/`: `Resource` e `UserResource`
- `database/migrations/`: schema atual
- `database/seed/`: seed inicial
- `tests/`: suíte integrada com MySQL

## Requisitos

- PHP 8.4
- Composer
- MySQL ou MariaDB
- `pdo_mysql`
- Redis em produção

## Instalação

```bash
git clone https://github.com/arthur-oliveira-oficial/parrot-php.git
cd parrot-php
composer install
cp .env.example .env
```

Exemplo mínimo de configuração:

```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=parrot_db
DB_DATABASE=parrot_db
DB_USER=root
DB_PASSWORD=

JWT_SECRET=troque_esta_chave
JWT_EXPIRY=3600
JWT_ISSUER=http://localhost:8000
JWT_AUDIENCE=parrot-api

ADMIN_NAME=Administrador
ADMIN_EMAIL=admin@parrot.com
ADMIN_PASSWORD=troque_esta_senha

CORS_ALLOWED_ORIGINS=http://localhost:3000
TRUSTED_PROXY_IPS=

RATE_LIMIT_MAX_REQUESTS=60
RATE_LIMIT_WINDOW_SECONDS=60
RATE_LIMIT_LOGIN_MAX_REQUESTS=5
RATE_LIMIT_LOGIN_WINDOW_SECONDS=900

CACHE_STORE=auto
CACHE_PREFIX=parrot:

REDIS_SCHEME=tcp
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DATABASE=0
REDIS_PASSWORD=
REDIS_TIMEOUT=1.5
```

Observações:

- `config/container.php` usa `DB_DATABASE` com fallback para `DB_NAME`.
- `database/scripts/migrate.php` e `database/scripts/seed.php` leem `DB_NAME`.
- `JWT_SECRET` é obrigatório.

## Banco e Seed

```bash
php database/scripts/migrate.php
php database/scripts/seed.php
```

Schema atual:

- `usuarios`
- `tokens_revogados`
- `migrations`

O seed inicial cria um administrador com `ADMIN_NAME`, `ADMIN_EMAIL` e `ADMIN_PASSWORD`.

## Executando Localmente

Servidor embutido do PHP:

```bash
php -S localhost:8000 -t public
```

Caddy/FrankenPHP:

```bash
caddy run
```

O `Caddyfile` atual expõe a aplicação em `:8080`.

## Rotas Atuais

Autenticação:

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`

Usuários:

- `GET /api/usuarios`
- `GET /api/usuarios/{id}`
- `POST /api/usuarios`
- `PUT /api/usuarios/{id}`
- `DELETE /api/usuarios/{id}`

Middlewares de rota:

- `POST /api/auth/login`: alias `rate_limit_login`
- demais rotas acima, exceto login: `JwtAuthMiddleware`

## Segurança Implementada

- JWT manual com `iss`, `aud`, `sub`, `jti`, `iat`, `nbf` e `exp`
- cookie `token` com `HttpOnly` e `SameSite=Strict`
- `Secure` ativado em HTTPS, produção ou proxy confiável com `X-Forwarded-Proto=https`
- blacklist de JWT revogado em banco e cache
- rate limit por `sub` do JWT válido ou IP como fallback
- proteção contra IDOR em `UserController`
- alteração de email ou senha exige `senha_atual`
- senhas com `PASSWORD_ARGON2ID`
- `UserResource` remove `senha` das respostas

## Controle de Acesso

- `GET /api/usuarios`: apenas admin
- `GET /api/usuarios/{id}`: admin ou o próprio usuário
- `POST /api/usuarios`: apenas admin
- `PUT /api/usuarios/{id}`: admin ou o próprio usuário
- `DELETE /api/usuarios/{id}`: admin ou o próprio usuário

Usuários criados pela API entram sempre como `tipo=user`.

## Middlewares Globais

Ordem real em `config/middlewares.php`:

1. `ErrorHandlerMiddleware`
2. `SecurityHeadersMiddleware`
3. `RateLimitMiddleware`
4. `CorsMiddleware`
5. `CsrfGuardMiddleware`

## Resposta JSON

Exemplo de sucesso:

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

Exemplo de erro:

```json
{
  "error": "Erro de validação",
  "errors": {
    "senha": "O campo senha deve ter pelo menos 8 caracteres, contendo letras maiúsculas, minúsculas e números."
  }
}
```

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
- `tests/TestCase.php` cria o banco se necessário
- as tabelas são recriadas a cada teste

## Produção

O container trata produção de forma mais rígida:

- `APP_ENV=production` não carrega `.env` automaticamente em `public/index.php`
- `CACHE_STORE=array`, `memory` e `apcu` são rejeitados
- Redis passa a ser obrigatório para rate limit e blacklist distribuídos

Configuração mínima esperada:

```env
APP_ENV=production
APP_DEBUG=false
CACHE_STORE=redis
```

Também ajuste:

- `JWT_SECRET` forte
- HTTPS
- `TRUSTED_PROXY_IPS` quando houver proxy reverso

## Documentação Relacionada

- [Instalação](docs/instalacao.md)
- [Diagrama](docs/diagrama.md)

## Licença

MIT
