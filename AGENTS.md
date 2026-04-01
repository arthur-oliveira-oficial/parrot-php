# AGENTS.md

## Contexto

Você está trabalhando no framework proprietário **Parrot PHP**, um micro-framework REST em PHP 8.4 com:

- `nikic/fast-route` para roteamento
- `php-di/php-di` para injeção de dependências
- `nyholm/psr7` e PSR-7/PSR-15 para request/response e middlewares
- `illuminate/database` como ORM principal
- JWT manual com cookie HttpOnly
- testes integrados contra **MySQL/MariaDB**, não SQLite

O projeto é uma API JSON. O código de referência é o próprio repositório atual. Se alguma documentação divergir do código, **o código vence**.

## Arquitetura Atual

Fluxo principal:

`public/index.php` -> `App\Core\Application` -> middlewares globais -> `App\Core\FastRouteRouter` -> middleware de rota -> controller -> model -> resource/view -> resposta JSON

Componentes ativos:

- `config/container.php`: definições do PHP-DI e configurações vindas do `.env`
- `config/routes.php`: rotas da API
- `config/middlewares.php`: middlewares globais
- `src/Controllers`: lógica HTTP
- `src/Models`: acesso a dados via Eloquent
- `src/Views`: formatação de resposta
- `src/Middlewares`: segurança, CORS, autenticação, rate limit e tratamento de erro
- `database/migrations`: schema
- `database/seed`: seed inicial
- `tests`: testes de integração e unidade

Domínio hoje implementado:

- autenticação: `AuthController`
- usuários: `UserController`, `UserModel`, `UserResource`
- blacklist de JWT: `TokenRevogado`
- cache de suporte: `ArrayKeyValueStore`, `ApcuKeyValueStore`, `RedisKeyValueStore`

## Regras Mandatórias

1. Todo arquivo PHP novo deve iniciar com `declare(strict_types=1);`.
2. Ao editar arquivos antigos sem `strict_types`, preserve o comportamento e adicione `strict_types` quando a mudança for segura.
3. Diretórios, nomes de arquivos e nomes físicos de artefatos do projeto devem permanecer em **inglês**.
4. Todo código novo deve usar **pt-BR** em nomes de variáveis, métodos, comentários, mensagens e payloads JSON.
5. Ao criar arquivos novos, mantenha a estrutura externa em inglês e o conteúdo interno do código em pt-BR.
6. A API deve continuar retornando **JSON padronizado** via `App\Core\Response` ou classes de `src/Views`.
7. Controllers e middlewares devem receber dependências por construtor e serem resolvidos pelo container.
8. Em código de aplicação, use **Eloquent**. Não introduza SQL raw em controllers, models ativos ou serviços HTTP.
9. Senhas devem usar `PASSWORD_ARGON2ID`.
10. Rotas protegidas devem usar `JwtAuthMiddleware`.
11. Nunca confiar em input do cliente. Validar body, query params, ids de rota e permissões.
12. Não reintroduza SQLite em testes, scripts ou documentação operacional.

## Convenções Reais do Projeto

### Banco e persistência

- O model ativo base é `src/Models/EloquentModel.php`.
- `src/Models/Model.php` é um legado em PDO puro. Não use esse model como base para novos recursos.
- Exceções limitadas: infraestrutura de teste e seed ainda usam PDO diretamente:
  - `tests/TestCase.php`
  - `database/seed/001_admin.php`
- Fora desses pontos de infraestrutura, prefira sempre Eloquent.

### Autenticação

- O login gera JWT manualmente em `AuthController`.
- O token é entregue em cookie `token` com `HttpOnly` e `SameSite=Strict`.
- O middleware `JwtAuthMiddleware` aceita o token **exclusivamente do cookie**.
- O logout também revoga o token com base no cookie.
- Existe blacklist persistida em `tokens_revogados` e cacheada por `TokenRevogado`.
- Não implemente autenticação principal via header `Authorization` em novas rotas protegidas.

### Rate limit

- O projeto usa `RateLimitMiddleware`.
- Em usuários autenticados, o identificador preferencial é o `sub` do JWT validado.
- Sem JWT válido, o fallback é IP.
- Login usa rate limit específico via alias `'rate_limit_login'` definido no container.

### Respostas

- Use `Resource` quando houver transformação de payload.
- Use `Response::json()`, `Response::error()`, `Response::unauthorized()` etc. para respostas simples.
- `UserResource` remove `senha` das respostas. Nunca exponha esse campo.

### Controle de acesso

- Siga o padrão já usado em `UserController`:
  - admin acessa recursos administrativos
  - usuário comum acessa apenas o próprio recurso quando aplicável
- Proteja contra IDOR verificando o `user_id` e o `user_tipo` presentes na request.

### Validação

- O controller base tem um validador simples com regras como:
  - `required`
  - `email`
  - `integer`
  - `strong_password`
  - `min:N`
  - `max:N`
- Se a validação do recurso seguir esse padrão, reutilize-o antes de criar algo novo.

## Estrutura de Diretórios

- `public/`: front controller
- `config/`: container, rotas e middlewares
- `src/Core/`: kernel HTTP e infraestrutura central
- `src/Controllers/`: endpoints
- `src/Middlewares/`: pipeline HTTP
- `src/Models/`: models Eloquent e suporte de persistência
- `src/Views/`: resources/transformers
- `src/Exceptions/`: exceções HTTP
- `src/Cache/`: armazenamento abstrato para cache e rate limit
- `database/migrations/`: migrations
- `database/seed/`: seeds
- `database/scripts/`: runners CLI de migration/seed
- `tests/`: suíte PHPUnit
- `docs/`: documentação auxiliar, potencialmente desatualizada

## Arquivos Sensíveis

Evite modificar sem necessidade explícita:

- `src/Core/Application.php`
- `src/Core/FastRouteRouter.php`
- `src/Core/MiddlewareQueue.php`
- `public/index.php`

Se precisar alterar esses arquivos, mantenha compatibilidade com PSR-7/PSR-15 e com o bootstrap atual.

## Padrões para Novos Recursos

### Nova rota

Registrar em `config/routes.php` no formato:

```php
'GET /api/recurso' => [MeuController::class, 'index'],
'POST /api/recurso' => [MeuController::class, 'store', JwtAuthMiddleware::class],
```

Se for endpoint sensível e público, considere alias de rate limit específico no container, como já ocorre no login.

### Novo controller

- Estender `App\Controllers\Controller`
- Receber model/resource no construtor
- Ler body via `getBody()`
- Validar input
- Retornar `ResponseInterface`
- Preferir `Resource` para payloads

### Novo model

- Estender `EloquentModel`
- Definir `$table`, `$fillable`, `$casts`
- Usar nomes e colunas em pt-BR quando fizer sentido com o domínio
- Evitar mass assignment inseguro

### Nova resource

- Estender `App\Views\Resource`
- Remover dados sensíveis
- Padronizar `item()`, `collection()`, `created()`, `updated()`, `deleted()`

## Segurança

Checklist mínimo antes de concluir qualquer alteração:

1. A rota exige `JwtAuthMiddleware` quando deveria exigir?
2. Há verificação de autorização além da autenticação?
3. A entrada foi validada e tipada?
4. Não há SQL raw novo em código de aplicação?
5. Senhas usam Argon2id?
6. O JWT continua trafegando por cookie HttpOnly?
7. Nenhum campo sensível está sendo retornado pela API?
8. O rate limit precisa de ajuste para o novo endpoint?

## Testes

Comandos padrão:

```bash
./vendor/bin/phpunit
./vendor/bin/phpunit --filter NomeDoTeste
```

Premissas reais da suíte:

- `phpunit.xml` força `APP_ENV=testing`
- `DB_DRIVER=mysql`
- banco de teste esperado: `parrot_test`
- `tests/TestCase.php` cria o banco se necessário e recria tabelas a cada teste

Não adapte testes para SQLite.

## Estado Atual Que Deve Ser Respeitado

- Existem arquivos ainda sem `declare(strict_types=1);` em `config/`, parte de `src/Core/`, alguns middlewares, `Resource`, `Controller` base e scripts de banco.
- Ao tocar nesses arquivos, prefira deixá-los melhores do que encontrou, sem refactors desnecessários.
- Há documentação em `docs/` com pontos históricos e alguns trechos divergentes do código atual.
- O seed do admin usa PDO preparado. Não propague esse padrão para a camada HTTP.

## Diretriz de Edição

Ao receber uma solicitação:

- primeiro confirme como o código atual implementa o fluxo
- depois siga o padrão já existente, não um padrão imaginado
- mantenha compatibilidade com o container, rotas e testes atuais
- mantenha diretórios e arquivos em inglês
- preserve pt-BR
- preserve a arquitetura enxuta do framework

Se houver ambiguidade entre o `AGENTS.md` e o repositório, ajuste sua decisão ao comportamento efetivo do código.
