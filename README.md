# **🦜 Parrot PHP \- RESTful API**

Uma API RESTful robusta, performática e estruturada desenvolvida em **PHP puro**, adotando as melhores práticas de desenvolvimento, Design Patterns e arquitetura limpa, sem a sobrecarga de frameworks full-stack pesados.

O **Parrot PHP** utiliza os melhores pacotes da comunidade (como Eloquent ORM e FastRoute) integrados de forma transparente através de um container de injeção de dependências e um pipeline de middlewares.

## **✨ Principais Recursos**

* **Arquitetura MVC & API Resources:** Estrutura organizada com Controllers limpos e camada de visualização (Resources) para padronização das respostas JSON.  
* **Roteamento de Alta Performance:** Utiliza FastRoute para resolução ultrarrápida de rotas HTTP.  
* **Banco de Dados & ORM:** Integração completa com Illuminate\\Database (Eloquent ORM), proporcionando um modelo de dados elegante e seguro.  
* **Sistema de Migrations e Seeds:** Scripts nativos para criação e popularização do banco de dados (migrate.php e seed.php).  
* **Autenticação Segura (JWT):** Sistema de login com tokens JWT (firebase/php-jwt), incluindo controle de **Tokens Revogados** (blacklist) para maior segurança.  
* **Pipeline de Middlewares:**  
  * 🛡️ SecurityHeadersMiddleware: Proteção contra ataques comuns (XSS, Clickjacking).  
  * 🚥 RateLimitMiddleware: Controle de requisições abusivas.  
  * 🔑 JwtAuthMiddleware: Proteção de rotas privadas.  
  * 🌐 CorsMiddleware: Configuração flexível de Cross-Origin.  
  * 🐛 ErrorHandlerMiddleware: Tratamento global e padronizado de exceções e erros HTTP.  
* **Testes Automatizados:** Cobertura de testes unitários e de integração utilizando PHPUnit.  
* **Pronto para Produção:** Acompanha configuração otimizada para o servidor web **Caddy** (Caddyfile).

## **🛠️ Tecnologias e Bibliotecas**

* **Core:** PHP 8.1+  
* **Roteamento:** nikic/fast-route  
* **Banco de Dados (ORM):** illuminate/database (Laravel Eloquent)  
* **Autenticação:** firebase/php-jwt  
* **Testes:** phpunit/phpunit  
* **Servidor Web Recomendado:** Caddy Server

## **📂 Estrutura do Projeto**

parrot-php/  
├── config/                \# Configurações gerais (Rotas, Middlewares, DI Container)  
├── database/              \# Estrutura de BD  
│   ├── migrations/        \# Classes de criação de tabelas  
│   ├── scripts/           \# Scripts CLI de execução (migrate/seed)  
│   └── seed/              \# Dados iniciais de teste/admin  
├── docs/                  \# Documentações adicionais (Deploy)  
├── public/                \# Document Root, Ponto de entrada (index.php)  
├── src/                   \# Código-fonte principal da aplicação  
│   ├── Controllers/       \# Lógica de negócio HTTP  
│   ├── Core/              \# Núcleo do mini-framework (App, Request, Response, Router)  
│   ├── Exceptions/        \# Exceções HTTP personalizadas  
│   ├── Middlewares/       \# Interceptadores de requisição HTTP  
│   ├── Models/            \# Modelos do Eloquent ORM  
│   └── Views/             \# Resources para formatação de JSON  
├── tests/                 \# Testes automatizados (PHPUnit)  
├── .env.example           \# Variáveis de ambiente de exemplo  
├── Caddyfile              \# Configuração do servidor Caddy  
└── composer.json          \# Gerenciamento de dependências

## **🚀 Instalação e Configuração (Desenvolvimento)**

### **Pré-requisitos**

* PHP 8.1 ou superior (com extensões pdo, pdo\_mysql ou pdo\_sqlite, mbstring)  
* Composer  
* Banco de dados (MySQL/MariaDB, PostgreSQL ou SQLite)

### **Passos**

1. **Clone o repositório:**  
   git clone \[https://github.com/arthur-oliveira-oficial/parrot-php.git\](https://github.com/arthur-oliveira-oficial/parrot-php.git)  
   cd parrot-php

2. **Instale as dependências:**  
   composer install

3. **Configure as Variáveis de Ambiente:**  
   Copie o arquivo de exemplo e edite com as suas credenciais de banco de dados e chave secreta JWT.  
   cp .env.example .env

4. **Prepare o Banco de Dados:**  
   Execute as migrações para criar as tabelas e as seeds para criar o usuário administrador padrão.  
   php database/scripts/migrate.php  
   php database/scripts/seed.php

   *(Nota: O usuário padrão criado pela seed geralmente é admin@admin.com)*  
5. **Inicie o Servidor de Desenvolvimento:**  
   php \-S localhost:8000 \-t public

   A API estará disponível em http://localhost:8000.

## **📡 Endpoints da API**

Abaixo está um resumo das rotas disponíveis. Todas as respostas utilizam o padrão JSON estabelecido pela classe Resource.

### **Autenticação**

* POST /auth/login \- Autentica um usuário e retorna o token JWT.

### **Usuários**

* GET /users \- Lista todos os usuários (Requer JWT).  
* GET /users/{id} \- Retorna os dados de um usuário específico (Requer JWT).  
* POST /users \- Cria um novo usuário.  
* PUT /users/{id} \- Atualiza os dados de um usuário (Requer JWT).  
* DELETE /users/{id} \- Remove um usuário do sistema (Requer JWT).

**Atenção:** Para acessar as rotas protegidas, inclua o header HTTP: Authorization: Bearer SEU\_TOKEN\_AQUI.

## **🧪 Executando os Testes**

O projeto possui cobertura de testes cobrindo CRUD de usuários e processos de autenticação. Para executá-los, utilize o PHPUnit:

./vendor/bin/phpunit

As configurações de teste já utilizam um banco de dados em memória (sqlite:memory) para garantir isolamento e rapidez, conforme definido no phpunit.xml.

## **🌍 Deploy em Produção**

O projeto está otimizado para rodar de forma extremamente eficiente utilizando o servidor **Caddy**.

1. Certifique-se de configurar a variável APP\_ENV=production no arquivo .env.  
2. Configure o banco de dados de produção.  
3. Para instruções detalhadas de como configurar o Caddy e garantir a máxima segurança, consulte o arquivo dedicado de documentação:  
   👉 [docs/instrucoes-production.txt](http://docs.google.com/docs/instrucoes-production.txt)

## **📄 Licença**

Este projeto é de código aberto e está sob a licença [MIT](https://opensource.org/licenses/MIT). Sinta-se à vontade para utilizá-lo, modificá-lo e contribuir.