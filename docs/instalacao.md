# Instalação do Parrot PHP

Este guia segue o comportamento real do repositório atual.

## Requisitos

- PHP `8.4`
- Composer
- MySQL ou MariaDB
- Extensão `pdo_mysql`
- Extensão `mbstring`
- Extensão `json`
- Extensão `ctype`
- Redis apenas para produção, quando `CACHE_STORE=redis` ou `CACHE_STORE=auto` com exigência de cache distribuído

Dependências de Composer usadas pelo projeto:

- `nikic/fast-route`
- `php-di/php-di`
- `php-di/invoker`
- `nyholm/psr7`
- `nyholm/psr7-server`
- `illuminate/database`
- `illuminate/events`
- `predis/predis`
- `vlucas/phpdotenv`

## Instalação Rápida

```bash
git clone https://github.com/arthur-oliveira-oficial/parrot-php.git
cd parrot-php
composer install
cp .env.example .env
```

## Configuração do `.env`

Preencha ao menos estas variáveis:

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

JWT_SECRET=troque_esta_chave_por_um_valor_forte
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

Notas:

- `config/container.php` aceita `DB_DATABASE` e também faz fallback para `DB_NAME`.
- Os scripts de banco em `database/scripts/*.php` leem `DB_NAME`, então mantenha `DB_NAME` preenchido.
- `JWT_SECRET` vazio faz a aplicação falhar ao resolver `JwtService`.
- `APP_URL` também é usado para derivar a origem permitida da própria aplicação no CORS e no CSRF guard.

## Banco de Dados

Crie o banco configurado em `DB_NAME` e execute:

```bash
php database/scripts/migrate.php
php database/scripts/seed.php
```

O projeto cria hoje estas tabelas:

- `migrations`
- `usuarios`
- `tokens_revogados`

O seed `database/seed/001_admin.php` cria um administrador com:

- `nome` vindo de `ADMIN_NAME`
- `email` vindo de `ADMIN_EMAIL`
- `senha` com hash `PASSWORD_ARGON2ID`

## Executando Localmente

Servidor embutido do PHP:

```bash
php -S localhost:8000 -t public
```

Caddy com FrankenPHP, usando o `Caddyfile` do repositório:

```bash
caddy run
```

O `Caddyfile` atual publica `public/` em `:8080`.

## Fluxo de Inicialização

O bootstrap real é:

1. `public/index.php` registra um handler global de exceção.
2. Carrega `vendor/autoload.php`.
3. Carrega `.env` quando `APP_ENV` não é `production`.
4. Monta o container PHP-DI com `config/container.php`.
5. Inicializa `App\Core\DatabaseCapsule`.
6. Instancia `App\Core\Application`.
7. Carrega `config/routes.php` e `config/middlewares.php`.
8. Cria a request PSR-7 e processa a pipeline.

## Middlewares e Segurança

Ordem dos middlewares globais:

1. `ErrorHandlerMiddleware`
2. `SecurityHeadersMiddleware`
3. `RateLimitMiddleware`
4. `CorsMiddleware`
5. `CsrfGuardMiddleware`

Regras relevantes:

- `JwtAuthMiddleware` só entra em rotas protegidas.
- O JWT é aceito exclusivamente do cookie `token`.
- O cookie sai com `HttpOnly` e `SameSite=Strict`.
- `Secure` é aplicado em HTTPS, produção, ou `X-Forwarded-Proto=https` vindo de proxy confiável.
- Escritas autenticadas por cookie exigem `Origin` ou `Referer` compatíveis com a whitelist.

## Cache e Redis

O container escolhe a implementação de `KeyValueStoreInterface` assim:

- `testing`: sempre `ArrayKeyValueStore`
- `development` com `CACHE_STORE=auto`: tenta Redis, depois APCu, depois array
- `CACHE_STORE=redis`: exige conexão funcional com Redis
- `production`: exige cache distribuído e rejeita `array`, `memory` e `apcu`

Consequência prática:

- desenvolvimento pode rodar sem Redis
- produção deve usar Redis para rate limit e blacklist de JWT

## Testes

A suíte usa MySQL real. Não adapte para SQLite.

Executar tudo:

```bash
./vendor/bin/phpunit
```

Executar um teste específico:

```bash
./vendor/bin/phpunit --filter AuthTest
```

Premissas da suíte em `phpunit.xml` e `tests/TestCase.php`:

- `APP_ENV=testing`
- `DB_DRIVER=mysql`
- banco esperado: `parrot_test`
- o banco é criado se não existir
- todas as tabelas são recriadas a cada teste
- os seeds são executados a cada teste

## Produção

Para subir com o comportamento esperado do código atual:

```env
APP_ENV=production
APP_DEBUG=false
CACHE_STORE=redis
```

Checklist mínimo:

- definir `JWT_SECRET` forte
- publicar atrás de HTTPS
- preencher `TRUSTED_PROXY_IPS` se houver proxy reverso
- garantir Redis disponível
- injetar variáveis de ambiente pelo servidor ou orquestrador

Em produção, `public/index.php` não carrega `.env` por arquivo. As variáveis devem vir do ambiente do processo.
