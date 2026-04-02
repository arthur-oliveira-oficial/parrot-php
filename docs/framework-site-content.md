# Parrot PHP

Parrot PHP é um micro-framework REST para APIs JSON em PHP 8.4. O projeto foi construído para manter uma base enxuta, com fluxo HTTP explícito, dependências pequenas e comportamento previsível em produção. Hoje ele combina FastRoute para roteamento, PHP-DI para injeção de dependências, Nyholm PSR-7/PSR-15 para request/response e middlewares, Eloquent como ORM, autenticação JWT manual com cookie `HttpOnly` e testes integrados contra MySQL.

Este documento descreve o que o código do repositório implementa hoje e pode ser usado como base de conteúdo para um site institucional, landing page técnica ou documentação pública.

## Resumo Executivo

- Micro-framework REST em PHP 8.4 voltado para APIs JSON.
- Arquitetura baseada em PSR-7 e PSR-15.
- Roteamento com `nikic/fast-route`.
- Injeção de dependências com `php-di/php-di`.
- Persistência principal com `illuminate/database` via Eloquent.
- Autenticação JWT própria, assinada em `HS256`.
- Token entregue e lido exclusivamente pelo cookie `token`.
- Blacklist persistente de JWT revogado.
- Rate limit global e rate limit específico para login.
- Proteções de CORS, CSRF, headers de segurança e controle contra IDOR.
- Testes integrados usando MySQL real, sem SQLite.

## Proposta do Framework

O Parrot PHP foi desenhado para entregar uma base REST moderna sem carregar o peso de um framework full stack. Em vez de esconder o fluxo da aplicação atrás de convenções extensas, ele organiza o pipeline HTTP de forma direta:

`public/index.php` → `Application` → middlewares globais → `FastRouteRouter` → middleware de rota → controller → model/resource → resposta JSON

Essa abordagem favorece:

- leitura rápida do fluxo de execução
- bootstrap simples
- facilidade para depuração
- adoção gradual de camadas
- baixo acoplamento com abstrações mágicas

## Stack Técnica

### Linguagem e runtime

- PHP `^8.4`

### Dependências principais

- `nikic/fast-route`
- `php-di/php-di`
- `php-di/invoker`
- `nyholm/psr7`
- `nyholm/psr7-server`
- `illuminate/database`
- `illuminate/events`
- `predis/predis`
- `vlucas/phpdotenv`

### Padrões adotados

- PSR-4 para autoload
- PSR-7 para mensagens HTTP
- PSR-15 para middlewares e handlers
- PSR container para DI

## Arquitetura Atual

### Entrada da aplicação

O bootstrap real está em `public/index.php`. Esse ponto de entrada:

1. registra um handler global de exceção como último fallback
2. carrega o autoloader do Composer
3. carrega `.env` quando `APP_ENV` não é `production`
4. cria o container PHP-DI a partir de `config/container.php`
5. inicializa o `DatabaseCapsule`
6. instancia `App\Core\Application`
7. carrega rotas e middlewares via arquivos de configuração
8. processa a requisição HTTP e envia a resposta

### Core HTTP

#### `App\Core\Application`

É a fachada principal do framework. Responsabilidades:

- montar o ciclo de vida da requisição
- criar a request PSR-7 a partir das superglobais
- carregar middlewares globais
- delegar o despacho ao router
- enviar status, headers e body da resposta final

#### `App\Core\MiddlewareQueue`

Implementa a pipeline PSR-15 no formato onion. Cada middleware pode:

- executar lógica antes do próximo passo
- repassar a request
- interromper a cadeia com uma resposta própria

#### `App\Core\FastRouteRouter`

É o router central. Ele:

- registra rotas HTTP por método e caminho
- suporta parâmetros dinâmicos como `/api/usuarios/{id}`
- injeta atributos de rota na request
- resolve middleware de rota
- chama o controller final via container e Invoker
- usa cache de rotas em produção

#### `FastRouteControllerHandler`

Handler interno responsável por:

- instanciar controllers pelo container
- validar existência de classe e método
- invocar o método do controller com suporte a injeção automática do `request`

#### `App\Core\Response`

Factory estática para respostas HTTP JSON. O código atual oferece helpers como:

- `json()`
- `error()`
- `ok()`
- `created()`
- `notFound()`
- `unauthorized()`
- `forbidden()`
- `serverError()`
- `tooManyRequests()`
- `noContent()`

### Estrutura de diretórios

- `public/`: front controller
- `config/`: container, rotas e middlewares
- `src/Core/`: kernel HTTP, JWT, banco e helpers centrais
- `src/Controllers/`: endpoints
- `src/Middlewares/`: pipeline global e autenticação
- `src/Models/`: models Eloquent e blacklist de token
- `src/Views/`: resources de transformação JSON
- `src/Exceptions/`: exceções HTTP
- `src/Cache/`: abstração e implementações de cache
- `database/migrations/`: schema atual
- `database/seed/`: seed inicial
- `database/scripts/`: runners CLI de migration e seed
- `tests/`: suíte integrada com MySQL

## Injeção de Dependências e Configuração

O container é definido em `config/container.php`. Ele centraliza:

- configurações vindas do ambiente
- factories de serviços
- escolha do backend de cache
- instâncias de controllers, middlewares, models e resources

### Configurações disponíveis

#### Aplicação

- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`

#### Banco

- `DB_DRIVER`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_DATABASE`
- `DB_USER`
- `DB_PASSWORD`

Observação real do código:

- `config/container.php` usa `DB_DATABASE` com fallback para `DB_NAME`
- `database/scripts/*.php` leem `DB_NAME`

#### JWT

- `JWT_SECRET`
- `JWT_EXPIRY`
- `JWT_ISSUER`
- `JWT_AUDIENCE`

#### CORS, proxies e segurança

- `CORS_ALLOWED_ORIGINS`
- `TRUSTED_PROXY_IPS`

#### Rate limit

- `RATE_LIMIT_MAX_REQUESTS`
- `RATE_LIMIT_WINDOW_SECONDS`
- `RATE_LIMIT_LOGIN_MAX_REQUESTS`
- `RATE_LIMIT_LOGIN_WINDOW_SECONDS`

#### Cache e Redis

- `CACHE_STORE`
- `CACHE_PREFIX`
- `REDIS_SCHEME`
- `REDIS_HOST`
- `REDIS_PORT`
- `REDIS_DATABASE`
- `REDIS_PASSWORD`
- `REDIS_TIMEOUT`

### Seleção do backend de cache

O contrato de cache é `KeyValueStoreInterface` e as implementações atuais são:

- `ArrayKeyValueStore`
- `ApcuKeyValueStore`
- `RedisKeyValueStore`

Regras reais do container:

- em `testing`, o projeto usa `ArrayKeyValueStore`
- em `development`, `CACHE_STORE=auto` tenta Redis, depois APCu, depois array
- em `production`, `array`, `memory` e `apcu` são rejeitados
- em `production`, Redis é obrigatório para suportar rate limit e blacklist distribuídos

## Banco de Dados e Persistência

### ORM

O framework usa Eloquent fora do Laravel por meio de `App\Core\DatabaseCapsule`. A classe inicializa a conexão, ativa acesso global ao capsule e dá boot no Eloquent.

### Model base

`App\Models\EloquentModel` estende `Illuminate\Database\Eloquent\Model` e configura:

- timestamps automáticos
- ocultação de `senha` na serialização
- serialização consistente de datas

### Schema atual

#### Tabela `usuarios`

Campos implementados hoje:

- `id`
- `nome`
- `email`
- `senha`
- `tipo`
- `created_at`
- `updated_at`
- `deletado_em`

Características:

- `email` único
- `tipo` limitado a `admin` e `user`
- soft delete via `deletado_em`

#### Tabela `tokens_revogados`

Campos implementados hoje:

- `id`
- `jti`
- `revogado_em`
- `expires_at`

Características:

- `jti` único
- índice em `jti`
- índice em `expires_at`

### Seed inicial

O seed `database/seed/001_admin.php` cria o administrador inicial com:

- `nome` via `ADMIN_NAME`
- `email` via `ADMIN_EMAIL`
- `senha` hasheada com `PASSWORD_ARGON2ID`
- `tipo=admin`

## Sistema de Autenticação

### Estratégia

O projeto usa JWT manual com assinatura `HS256`, sem biblioteca externa específica de autenticação. A geração e validação ficam em `App\Core\JwtService`.

### Claims implementadas

O payload atual inclui:

- `sub`
- `email`
- `tipo`
- `jti`
- `iss`
- `aud`
- `iat`
- `nbf`
- `exp`

### Entrega do token

No login, o token é enviado exclusivamente por cookie:

- nome: `token`
- `HttpOnly`
- `SameSite=Strict`
- `Path=/`
- `Secure` quando aplicável

O `Secure` é ativado quando:

- a requisição já está em HTTPS
- o ambiente é `production`
- ou `X-Forwarded-Proto=https` vem de um proxy confiável

### Validação do token

`JwtService::validarToken()` verifica:

- formato em 3 partes
- assinatura HMAC
- `typ=JWT`
- `alg=HS256`
- `iss`
- `aud`
- existência de `sub`
- `nbf`
- `iat`
- `exp`

### Middleware de autenticação

`App\Middlewares\JwtAuthMiddleware`:

- lê o token exclusivamente do cookie `token`
- rejeita ausência de token com `401`
- rejeita token inválido ou expirado com `401`
- rejeita token revogado com `401`
- adiciona à request:
  - `user_id`
  - `user_email`
  - `user_tipo`
  - `jwt_payload`

### Logout e revogação

O logout:

- lê o `jwt_payload` já validado
- persiste o `jti` em `tokens_revogados`
- grava esse estado em cache
- remove o cookie com `Max-Age=0`
- limpa tokens expirados da blacklist

### Blacklist de tokens

`App\Models\TokenRevogado` combina:

- persistência em banco
- cache por backend configurado
- cache local em memória por processo

Isso reduz consulta repetida ao banco em verificações de revogação.

## Segurança Implementada

### Rate limit

`App\Middlewares\RateLimitMiddleware` protege a API com contador e TTL.

Comportamento real:

- ignora `OPTIONS`
- usa `sub` do JWT válido como identificador preferencial
- cai para IP quando não há JWT válido
- só confia em `CF-Connecting-IP` e `X-Forwarded-For` se `REMOTE_ADDR` estiver em `TRUSTED_PROXY_IPS`
- responde com `429` e inclui:
  - `Retry-After`
  - `X-RateLimit-Limit`
  - `X-RateLimit-Remaining`
  - `X-RateLimit-Reset`

Configuração padrão atual:

- global: 60 requisições por 60 segundos
- login: 5 tentativas por 900 segundos

### CORS

`App\Middlewares\CorsMiddleware`:

- usa whitelist explícita de origens
- responde preflight `OPTIONS`
- envia `Access-Control-Allow-Origin`
- envia `Access-Control-Allow-Credentials`
- limita métodos e headers permitidos

Origens permitidas são formadas por:

- `CORS_ALLOWED_ORIGINS`
- origem derivada de `APP_URL`

### Proteção CSRF

`App\Middlewares\CsrfGuardMiddleware` protege operações autenticadas por cookie.

Comportamento:

- ignora métodos seguros como `GET`, `HEAD` e `OPTIONS`
- só entra em ação quando existe cookie `token`
- exige `Origin` ou `Referer` válido
- bloqueia escritas autenticadas vindas de origem fora da whitelist

### Headers de segurança

`App\Middlewares\SecurityHeadersMiddleware` adiciona:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Content-Security-Policy`
- `Permissions-Policy`
- `Cache-Control: no-store, max-age=0`
- `Strict-Transport-Security` quando a conexão é HTTPS confiável

### Tratamento de erros

`App\Middlewares\ErrorHandlerMiddleware` converte erros em JSON.

Regras:

- `HttpException` gera status correspondente
- exceções genéricas viram `500`
- `ModelNotFoundException` vira `404`
- em desenvolvimento pode expor a mensagem real
- em produção retorna mensagem segura e genérica

### Proteções de domínio aplicadas

O código atual já contém:

- prevenção contra IDOR em `UserController`
- validação de `id` como inteiro positivo
- exigência de `senha_atual` para alterar email ou senha
- hash de senha com `PASSWORD_ARGON2ID`
- rehash automático quando necessário
- remoção do campo `senha` em todas as respostas de usuário

## Middlewares Globais

A ordem real em `config/middlewares.php` é:

1. `ErrorHandlerMiddleware`
2. `SecurityHeadersMiddleware`
3. `RateLimitMiddleware`
4. `CorsMiddleware`
5. `CsrfGuardMiddleware`

Consequência prática:

- erros são capturados do lado mais externo
- os headers de segurança entram em praticamente todas as respostas
- o rate limit acontece antes do controller
- CORS e CSRF são tratados ainda dentro da pipeline global

## API Implementada Hoje

### Autenticação

#### `POST /api/auth/login`

Função:

- autentica o usuário por email e senha
- gera JWT
- devolve os dados do usuário
- envia o cookie `token`

Middleware de rota:

- `rate_limit_login`

Resposta de sucesso:

```json
{
  "data": {
    "id": 1,
    "nome": "Administrador",
    "email": "admin@parrot.com",
    "tipo": "admin"
  },
  "message": "Login realizado com sucesso"
}
```

#### `POST /api/auth/logout`

Função:

- revoga o token atual
- remove o cookie

Middleware de rota:

- `JwtAuthMiddleware`

#### `GET /api/auth/me`

Função:

- retorna os dados do usuário autenticado

Middleware de rota:

- `JwtAuthMiddleware`

### Usuários

#### `GET /api/usuarios`

Função:

- lista usuários com paginação

Permissão:

- apenas `admin`

Query params:

- `page` padrão `1`
- `limit` padrão `20`, máximo `100`

Formato de resposta:

```json
{
  "data": [],
  "meta": {
    "pagina_atual": 1,
    "por_pagina": 20,
    "total_registros": 0,
    "total_paginas": 0
  }
}
```

#### `GET /api/usuarios/{id}`

Função:

- retorna um usuário específico

Permissão:

- `admin` pode acessar qualquer usuário
- usuário comum só acessa o próprio registro

#### `POST /api/usuarios`

Função:

- cria novo usuário

Permissão:

- apenas `admin`

Regra de domínio:

- usuários criados via API sempre entram como `tipo=user`

#### `PUT /api/usuarios/{id}`

Função:

- atualiza `nome`, `email` e `senha`

Permissão:

- `admin` ou o próprio usuário

Regras de segurança:

- email e senha exigem `senha_atual`
- campos fora da whitelist são descartados

#### `DELETE /api/usuarios/{id}`

Função:

- remove usuário com soft delete

Permissão:

- `admin` ou o próprio usuário

## Regras de Validação

O controller base já fornece um validador simples com estas regras:

- `required`
- `email`
- `integer`
- `strong_password`
- `min:N`
- `max:N`

### Regras reais de senha forte

Hoje `strong_password` exige:

- mínimo de 8 caracteres
- pelo menos uma letra maiúscula
- pelo menos uma letra minúscula
- pelo menos um número
- máximo de 128 caracteres

## Resources e Formato de Resposta

### `App\Views\Resource`

Padroniza respostas como:

- item: `{ "data": { ... } }`
- coleção: `{ "data": [ ... ], "meta": { ... } }`
- erro de validação: `{ "error": "...", "errors": { ... } }`
- criação, atualização e exclusão com `message`

### `App\Views\UserResource`

Responsabilidades:

- remover `senha`
- transformar payloads de usuário
- padronizar respostas de login

## Controle de Acesso Atual

O domínio implementado hoje segue estas regras:

- `admin` lista todos os usuários
- `admin` acessa qualquer usuário
- usuário comum acessa apenas o próprio recurso quando aplicável
- criação de usuários pela API é exclusiva de admin
- alteração e exclusão são restritas a admin ou ao próprio titular

## Modelos Implementados

### `UserModel`

Responsabilidades:

- criação de usuário comum
- criação interna de administrador
- busca com e sem soft delete
- paginação
- atualização
- soft delete
- verificação de senha
- rehash de senha
- verificação de email existente inclusive em usuários deletados

### `TokenRevogado`

Responsabilidades:

- registrar tokens revogados
- consultar revogação por `jti`
- cachear revogações
- limpar expirados

## Experiência de Desenvolvimento

### Instalação

Passos mínimos:

```bash
git clone https://github.com/arthur-oliveira-oficial/parrot-php.git
cd parrot-php
composer install
```

### Configuração mínima de ambiente

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

### Banco e seed

```bash
php database/scripts/migrate.php
php database/scripts/seed.php
```

### Execução local

Servidor embutido do PHP:

```bash
php -S localhost:8000 -t public
```

Caddy/FrankenPHP com o arquivo do repositório:

```bash
caddy run
```

## Testes

A suíte automatizada valida autenticação, CRUD, cache e regras de produção.

Comandos:

```bash
./vendor/bin/phpunit
./vendor/bin/phpunit --filter AuthTest
```

Premissas reais da suíte:

- `APP_ENV=testing`
- `DB_DRIVER=mysql`
- banco esperado `parrot_test`
- o banco é criado se necessário
- as tabelas são recriadas a cada teste
- os seeds rodam a cada teste

Casos já cobertos:

- login e logout
- revogação de token
- acesso ao endpoint `/me`
- rate limit por usuário autenticado na mesma rede
- CRUD de usuários
- restrições de admin
- proteção contra IDOR
- exigência de senha atual
- restrições de cache em produção
- confiança condicionada em proxy reverso
- bloqueio de escrita autenticada com origem externa

## Produção

O código é mais rígido em produção.

### Comportamento atual

- `public/index.php` não carrega `.env` automaticamente quando `APP_ENV=production`
- o processo deve receber variáveis do ambiente
- cache distribuído passa a ser obrigatório
- `CACHE_STORE=array`, `memory` e `apcu` são recusados

### Configuração mínima recomendada

```env
APP_ENV=production
APP_DEBUG=false
CACHE_STORE=redis
```

Checklist operacional:

- definir `JWT_SECRET` forte
- habilitar HTTPS
- configurar `TRUSTED_PROXY_IPS` quando houver proxy reverso
- manter Redis disponível
- configurar `APP_URL` corretamente
- publicar CORS apenas para origens conhecidas

## Limitações e Escopo Atual

O framework implementa hoje um núcleo funcional e seguro para API JSON, mas o escopo ainda é propositalmente enxuto. O repositório atual não expõe, por exemplo:

- sistema de módulos ou plugins do framework
- autenticação via `Authorization: Bearer`
- refresh token
- filas, eventos de domínio ou scheduler
- documentação OpenAPI gerada automaticamente
- camada administrativa pronta
- abstração de CLI própria além dos scripts de banco

Essa limitação não é um defeito do projeto; é uma decisão de escopo. O valor atual está na simplicidade, previsibilidade e base segura para evoluir uma API REST.

## Diferenciais Técnicos

- fluxo HTTP simples de seguir
- uso de padrões PSR em vez de contratos proprietários
- JWT próprio com validação explícita
- autenticação por cookie `HttpOnly`, reduzindo exposição do token no frontend
- blacklist persistente de logout
- rate limit sensível ao usuário autenticado
- proteção CSRF alinhada ao uso de cookie
- produção com exigência de cache distribuído
- testes integrados com banco real

## Sugestão de Estrutura para Site

Se o conteúdo for quebrado em páginas ou seções, uma estrutura adequada é:

1. Hero com proposta do framework e stack principal
2. Seção de diferenciais
3. Arquitetura e fluxo da requisição
4. Segurança e autenticação
5. Endpoints e domínio já implementado
6. Instalação e ambiente
7. Produção e observabilidade operacional
8. Testes e garantias
9. Limitações atuais e roadmap desejado

## Fontes internas usadas para este documento

Este material foi derivado da leitura do código atual em:

- `public/index.php`
- `config/container.php`
- `config/routes.php`
- `config/middlewares.php`
- `src/Core/*`
- `src/Middlewares/*`
- `src/Controllers/*`
- `src/Models/*`
- `src/Views/*`
- `database/migrations/*`
- `database/seed/*`
- `tests/*`

