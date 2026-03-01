# **🦜 Parrot PHP \- RESTful API**

Uma API RESTful robusta, performática e estruturada desenvolvida em **PHP 8.4+ puro**, adotando as melhores práticas de desenvolvimento, Design Patterns e arquitetura limpa, sem a sobrecarga de frameworks full-stack pesados.

O **Parrot PHP** utiliza os melhores pacotes da comunidade (como Eloquent ORM, FastRoute, PHP-DI e PSR-7) integrados de forma transparente através de um container de injeção de dependências e um pipeline de middlewares.

## **✨ Principais Recursos**

* **Arquitetura MVC & API Resources:** Estrutura organizada com Controllers limpos e camada de visualização (Resources) para padronização das respostas JSON.  
* **Roteamento de Alta Performance:** Utiliza FastRoute para resolução ultrarrápida de rotas HTTP.  
* **Injeção de Dependências:** Contêiner configurado usando PHP-DI (`config/container.php`).
* **Banco de Dados & ORM:** Integração completa com Illuminate\\Database (Eloquent ORM), proporcionando um modelo de dados elegante e seguro, estritamente configurado para MariaDB/MySQL.
* **Sistema de Migrations e Seeds:** Scripts nativos para criação e popularização do banco de dados (`database/scripts/migrate.php` e `database/scripts/seed.php`).
* **Autenticação Segura (JWT):** Sistema de login com tokens JWT (desenvolvido nativamente sem bibliotecas de terceiros para garantir segurança total), incluindo controle de **Tokens Revogados** (blacklist) e gerenciamento via cookies HttpOnly.
* **Pipeline de Middlewares:**  
  * 🛡️ `SecurityHeadersMiddleware`: Proteção contra ataques comuns (XSS, Clickjacking, e headers rígidos de CSP/HSTS).
  * 🚥 `RateLimitMiddleware`: Controle de requisições abusivas.
  * 🔑 `JwtAuthMiddleware`: Proteção de rotas privadas usando validação rigorosa de JWT.
  * 🌐 `CorsMiddleware`: Configuração flexível de Cross-Origin.
  * 🐛 `ErrorHandlerMiddleware`: Tratamento global e padronizado de exceções (`NotFoundException`, `BadRequestException`, etc.) e erros HTTP.
* **Segurança OWASP:** Prevenção estrita a SQL Injection sem uso de SQL bruto nas consultas PDO e uso obrigatório de Hashes seguros (Argon2id).
* **Testes Automatizados:** Cobertura de testes unitários e de integração utilizando PHPUnit e requisições HTTP locais estritamente em um banco de dados MariaDB.
* **Pronto para Produção:** Acompanha configuração otimizada para o servidor web **Caddy** (`Caddyfile`) e FrankenPHP.

## **🛠️ Tecnologias e Bibliotecas**

* **Core:** PHP 8.4+ (Uso restrito de Tipagem Forte nativa).
* **Roteamento:** nikic/fast-route  
* **Injeção de Dependências:** php-di/php-di
* **Banco de Dados (ORM):** illuminate/database (Laravel Eloquent ORM stand-alone)
* **HTTP PSR-7:** nyholm/psr7 e nyholm/psr7-server
* **Testes:** phpunit/phpunit  
* **Servidor Web Recomendado:** Caddy Server / FrankenPHP (Classic Mode)

## **📂 Estrutura do Projeto**

parrot-php/  
├── config/                # Configurações gerais (routes.php, container.php)
├── database/              # Estrutura de BD e SQLite
│   ├── migrations/        # Classes de criação de tabelas
│   ├── scripts/           # Scripts CLI de execução (migrate.php, seed.php)
│   └── seed/              # Dados iniciais de teste/admin
├── docs/                  # Documentações adicionais (Deploy)
├── public/                # Document Root, Ponto de entrada (index.php)
├── src/                   # Código-fonte principal da aplicação
│   ├── Controllers/       # Lógica de negócio HTTP
│   ├── Core/              # Núcleo do mini-framework (App, Request, Response, Router)
│   ├── Exceptions/        # Exceções HTTP personalizadas
│   ├── Middlewares/       # Interceptadores de requisição HTTP
│   ├── Models/            # Modelos do Eloquent ORM
│   └── Views/             # Resources para formatação de JSON
├── tests/                 # Testes automatizados (PHPUnit)
├── .env.example           # Variáveis de ambiente de exemplo
├── Caddyfile              # Configuração do servidor Caddy
└── composer.json          # Gerenciamento de dependências

## **🚀 Instalação e Configuração (Desenvolvimento)**

### **Pré-requisitos**

* PHP 8.4 ou superior (com extensões `pdo`, `pdo_mysql`, `mbstring`)
* Composer  
* Banco de dados MariaDB ou MySQL. **(SQLite não é suportado para os testes/framework principal)**.

### **Passos**

1. **Clone o repositório:**  
   ```bash
   git clone https://github.com/arthur-oliveira-oficial/parrot-php.git
   cd parrot-php
   ```

2. **Instale as dependências:**  
   ```bash
   composer install
   ```

3. **Configure as Variáveis de Ambiente:**  
   Copie o arquivo de exemplo e edite com as suas credenciais de banco de dados e chave secreta JWT.  
   ```bash
   cp .env.example .env
   ```

4. **Prepare o Banco de Dados:**  
   Execute as migrações para criar as tabelas e as seeds para criar o usuário administrador padrão.  
   ```bash
   php database/scripts/migrate.php  
   php database/scripts/seed.php
   ```
   *(Nota: O usuário padrão criado pela seed geralmente é admin@admin.com)*  

5. **Inicie o Servidor de Desenvolvimento:**
   ```bash
   php -S localhost:8000 -t public
   ```
   A API estará disponível em http://localhost:8000.

## **📡 Endpoints da API**

Abaixo está um resumo das rotas disponíveis. Todas as respostas utilizam o padrão JSON estabelecido pela classe `Resource`.

### **Autenticação**

* `POST /api/auth/login` - Autentica um usuário e retorna o token JWT em um cookie HttpOnly.
* `POST /api/auth/logout` - Revoga a sessão de usuário.
* `GET /api/auth/me` - Retorna as informações do usuário atual logado. (Requer JWT)

### **Usuários**

* `GET /api/usuarios` - Lista todos os usuários (Requer JWT).
* `GET /api/usuarios/{id}` - Retorna os dados de um usuário específico (Requer JWT).
* `POST /api/usuarios` - Cria um novo usuário (Requer JWT).
* `PUT /api/usuarios/{id}` - Atualiza os dados de um usuário (Requer JWT).
* `DELETE /api/usuarios/{id}` - Remove um usuário do sistema (Soft delete, Requer JWT).

**Atenção:** As rotas protegidas verificam a presença do cookie de autenticação JWT `token`.

## **🧪 Executando os Testes**

O projeto possui cobertura de testes para os fluxos principais utilizando PHPUnit.

Para executá-los, utilize:

```bash
./vendor/bin/phpunit
```

**ATENÇÃO:** As configurações de teste (`phpunit.xml`) exigem estritamente um banco de dados MariaDB/MySQL (ex: `parrot_test`). Certifique-se de que o banco de dados esteja acessível pelas configurações padrão do `phpunit.xml`.

## **🤖 Instruções para a IA (Inteligência Artificial)**

Ao interagir com este código e contribuir, a Inteligência Artificial deve ler, compreender e seguir rigorosamente as regras em `CLAUDE.md`.

Regras breves:
- Linguagem **PHP 8.4** com uso **obrigatório** de `declare(strict_types=1);`.
- Respostas, variáveis, classes e banco de dados **Sempre em pt-BR**.
- Estrita adesão às premissas de segurança **OWASP** descritas em `CLAUDE.md` (nunca retorne um bloco de erro try/catch vazio e nunca use Raw SQL; sempre utilize os Helpers da aplicação ou ORM Eloquent).

## **🌍 Deploy em Produção**

O projeto está otimizado para rodar de forma extremamente eficiente utilizando o servidor **Caddy** ou **FrankenPHP**.

1. Certifique-se de configurar a variável `APP_ENV=production` e `APP_DEBUG=false` no arquivo `.env`.
2. Configure o banco de dados de produção.  
3. Altere a `JWT_SECRET` para uma chave forte e nunca use credenciais default.
4. Para instruções detalhadas de como configurar o Caddy e garantir a máxima segurança, consulte a documentação ou configure os Caddyfiles baseados na raiz.

## **📄 Licença**

Este projeto é de código aberto e está sob a licença [MIT](https://opensource.org/licenses/MIT). Sinta-se à vontade para utilizá-lo, modificá-lo e contribuir.
