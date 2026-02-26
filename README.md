# Parrot PHP Framework

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Framework](https://img.shields.io/badge/Framework-PSR--15-orange)](https://www.php-fig.org/psr/psr-15/)

Um micro-framework PHP moderno para construção de APIs REST, construído com componentes Laravel e bibliotecas PSR-compliant.

## Características

- **PSR-7 Compliant**: Uso de bibliotecas PSR-7 para mensagens HTTP
- **PSR-15 Middlewares**: Pipeline de middleware padrão PSR-15
- **Routing Flexível**: FastRoute para dispatcher de rotas com suporte a parâmetros
- **ORM Eloquent**: Laravel Eloquent para abstração de banco de dados
- **JWT Authentication**: Autenticação via JSON Web Tokens
- **Dependency Injection**: PHP-DI para injeção de dependências
- **Rate Limiting**: Controle de taxa de requisições
- **CORS**: Suporte a Cross-Origin Resource Sharing
- **Security Headers**: Cabeçalhos de segurança automaticamente
- **Soft Deletes**: Suporte a deleção suave em modelos

## Requisitos

- PHP 8.4 ou superior
- MySQL 5.7+ / MariaDB 10.2+
- Composer

## Instalação

1. Clone o repositório:
```bash
git clone <repo-url> parrot-php
cd parrot-php
```

2. Instale as dependências:
```bash
composer install
```

3. Configure o arquivo `.env`:
```bash
cp .env.example .env
```

4. Edite o `.env` com suas configurações de banco de dados e JWT.

## Configuração

### Variáveis de Ambiente

```bash
# Aplicação
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Banco de Dados (MySQL/MariaDB)
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=parrot_db
DB_USER=root
DB_PASSWORD=sua_senha

# Autenticação JWT
JWT_SECRET=sua-chave-secreta
JWT_EXPIRY=3600

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000

# Rate Limiting
RATE_LIMIT_MAX_REQUESTS=60
RATE_LIMIT_WINDOW_SECONDS=60
```

## Estrutura do Projeto

```
parrot-php/
├── public/                      # Ponto de entrada web
│   └── index.php               # Front controller
├── src/
│   ├── Core/                   # Nucleo do framework
│   │   ├── Application.php    # Orquestrador principal (PSR-15)
│   │   ├── Router.php          # Roteador customizado
│   │   ├── FastRouteRouter.php # Adaptador FastRoute
│   │   ├── DatabaseCapsule.php # Configuração Eloquent
│   │   ├── Request.php         # Auxiliar de requisição
│   │   ├── Response.php        # Auxiliar de resposta
│   │   └── MiddlewareQueue.php # Pipeline de middleware
│   ├── Controllers/            # Controladores HTTP
│   │   ├── Controller.php     # Controlador base
│   │   ├── AuthController.php  # Autenticação
│   │   └── UserController.php  # CRUD de usuários
│   ├── Models/                 # Modelos Eloquent
│   │   ├── Model.php           # Modelo base PDO
│   │   ├── EloquentModel.php   # Modelo base Eloquent
│   │   └── UserModel.php       # Modelo de usuário
│   ├── Middlewares/            # Middlewares HTTP (PSR-15)
│   │   ├── CorsMiddleware.php
│   │   ├── JwtAuthMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   ├── SecurityHeadersMiddleware.php
│   │   └── ErrorHandlerMiddleware.php
│   ├── Views/                  # Formatadores de resposta
│   │   ├── Resource.php
│   │   └── UserResource.php
│   └── Exceptions/             # Exceções HTTP
│       ├── HttpException.php
│       ├── NotFoundException.php
│       ├── UnauthorizedException.php
│       ├── ForbiddenException.php
│       ├── BadRequestException.php
│       └── MethodNotAllowedException.php
├── config/
│   ├── routes.php             # Definições de rotas
│   ├── container.php          # Configuração PHP-DI
│   └── middlewares.php         # Stack de middlewares globais
├── database/
│   ├── migrations/            # Migrations
│   │   └── 001_create_usuarios_table.php
│   ├── seed/                  # Seeds
│   │   └── 001_admin.php
│   └── scripts/              # Scripts de utilidades
│       ├── migrate.php
│       └── seed.php
├── tests/                     # Testes PHPUnit
└── .env                      # Variáveis de ambiente
```

## Arquitetura

### Fluxo de Requisição

1. Requisição entra em `public/index.php`
2. Variáveis de ambiente são carregadas (phpdotenv)
3. Container PHP-DI é configurado
4. Middlewares globais executam (ErrorHandler, Security, RateLimit, CORS)
5. Roteador despacha para o controlador
6. Controlador retorna a resposta

### Stack de Middlewares (Ordem de Execução)

1. `ErrorHandlerMiddleware` - Captura todas as exceções
2. `SecurityHeadersMiddleware` - Adiciona cabeçalhos de segurança
3. `RateLimitMiddleware` - Limita taxa de requisições
4. `CorsMiddleware` - Cabeçalhos CORS

### Padrões PSR

| Padrão | Implementação |
|--------|---------------|
| PSR-3 | Log de erros (LoggerInterface) |
| PSR-7 | nyholm/psr7 |
| PSR-11 | php-di/php-di |
| PSR-15 | MiddlewareQueue + Application |
| PSR-17 | nyholm/psr7 (ResponseFactory) |

## Referência da API

### Autenticação

| Método | Endpoint | Middleware | Descrição |
|--------|----------|------------|-----------|
| POST | `/api/auth/login` | Nenhum | Login de usuário |
| POST | `/api/auth/logout` | Nenhum | Logout de usuário |
| GET | `/api/auth/me` | JWT | Obter usuário atual |

#### POST /api/auth/login

**Corpo da Requisição:**
```json
{
  "email": "admin@parrot.com",
  "senha": "admin123"
}
```

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Login realizado com sucesso",
  "data": {
    "id": 1,
    "nome": "Administrador",
    "email": "admin@parrot.com",
    "tipo": "admin"
  }
}
```

#### POST /api/auth/logout

**Resposta:**
```json
{
  "success": true,
  "message": "Logout realizado com sucesso"
}
```

#### GET /api/auth/me

**Resposta:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "nome": "Administrador",
    "email": "admin@parrot.com",
    "tipo": "admin",
    "created_at": "2025-01-01T00:00:00Z",
    "updated_at": "2025-01-01T00:00:00Z"
  }
}
```

### Usuários (CRUD)

| Método | Endpoint | Middleware | Descrição |
|--------|----------|------------|-----------|
| GET | `/api/usuarios` | JWT | Listar todos os usuários |
| GET | `/api/usuarios/{id}` | JWT | Obter usuário por ID |
| POST | `/api/usuarios` | JWT | Criar novo usuário |
| PUT | `/api/usuarios/{id}` | JWT | Atualizar usuário |
| DELETE | `/api/usuarios/{id}` | JWT | Deletar usuário |

> **Nota**: A autenticação JWT é feita via cookie httpOnly. O token é armazenado automaticamente pelo frontend.

#### GET /api/usuarios

**Resposta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nome": "Administrador",
      "email": "admin@parrot.com",
      "tipo": "admin",
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "total": 1
  }
}
```

#### GET /api/usuarios/{id}

**Resposta:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "nome": "Administrador",
    "email": "admin@parrot.com",
    "tipo": "admin",
    "created_at": "2025-01-01T00:00:00Z",
    "updated_at": "2025-01-01T00:00:00Z"
  }
}
```

#### POST /api/usuarios

**Corpo da Requisição:**
```json
{
  "nome": "Novo Usuário",
  "email": "novo@exemplo.com",
  "senha": "senha123",
  "tipo": "user"
}
```

**Resposta:**
```json
{
  "success": true,
  "message": "Usuário criado com sucesso",
  "data": {
    "id": 2,
    "nome": "Novo Usuário",
    "email": "novo@exemplo.com",
    "tipo": "user",
    "created_at": "2025-01-01T00:00:00Z",
    "updated_at": "2025-01-01T00:00:00Z"
  }
}
```

#### PUT /api/usuarios/{id}

**Corpo da Requisição:**
```json
{
  "nome": "Nome Atualizado",
  "tipo": "admin"
}
```

**Resposta:**
```json
{
  "success": true,
  "message": "Usuário atualizado com sucesso",
  "data": {
    "id": 2,
    "nome": "Nome Atualizado",
    "email": "novo@exemplo.com",
    "tipo": "admin",
    "updated_at": "2025-01-01T00:00:00Z"
  }
}
```

#### DELETE /api/usuarios/{id}

**Resposta:**
```json
{
  "success": true,
  "message": "Usuário deletado com sucesso"
}
```

## Modelo de Dados

### Tabela: usuarios

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | BIGINT UNSIGNED | Chave primária |
| nome | VARCHAR(255) | Nome do usuário |
| email | VARCHAR(255) | Email único |
| senha | VARCHAR(255) | Senha hasheada |
| tipo | ENUM('admin', 'user') | Tipo de usuário |
| created_at | TIMESTAMP | Data de criação |
| updated_at | TIMESTAMP | Data de atualização |
| deleted_at | TIMESTAMP | Data de deleção (soft delete) |

## Executando o Projeto

### Servidor de Desenvolvimento

```bash
php -S localhost:8000 -t public/
```

### Executando Migrations

```bash
php database/scripts/migrate.php
```

### Executando Seeds

```bash
php database/scripts/seed.php
```

### Credenciais Padrão (Seed)

- **Email**: admin@parrot.com
- **Senha**: admin123

## Testes

```bash
vendor/bin/phpunit
```

### Executando Testes Específicos

```bash
vendor/bin/phpunit tests/RouterTest.php
vendor/bin/phpunit tests/AuthTest.php
vendor/bin/phpunit tests/UserCrudTest.php
```

## Deployment

### Notas de Produção

1. **Configurações de Ambiente**:
   - Defina `APP_ENV=production`
   - Defina `APP_DEBUG=false`
   - Use uma chave JWT forte e única

2. **Banco de Dados**:
   - Use conexão SSL/TLS
   - Configure pool de conexões adequado
   - Backups regulares

3. **Segurança**:
   - Habilite HTTPS/SSL
   - Configure cabeçalhos CSP apropriados
   - Use rate limiting com Redis em produção

4. **Performance**:
   - Habilite cache de rotas (`ROUTE_CACHE=true`)
   - Use OPcache do PHP
   - Configure CDN para assets estáticos

### Arquivo de Configuração de Produção

Consulte `docs/instrucoes-production.txt` para instruções detalhadas de deployment.

## Dependências

| Componente | Biblioteca |
|------------|------------|
| Routing | nikic/fast-route |
| ORM | illuminate/database |
| Container | php-di/php-di |
| HTTP Messages | nyholm/psr7 |
| Environment | vlucas/phpdotenv |

## Licença

MIT License - lihat arquivo [LICENSE](LICENSE) para detalhes.
