# **🦜 Parrot PHP API**

Um projeto de API RESTful robusto, modular e escalável construído em PHP. Este projeto atua como um micro-framework customizado, aplicando as melhores práticas de desenvolvimento, como Injeção de Dependência (DI), padrão MVC moderno, Middlewares, Autenticação JWT e ORM.

## **✨ Características Principais**

* **Arquitetura Limpa:** Separação clara de responsabilidades (Controllers, Models, Views/Resources, Middlewares, Exceptions).  
* **Roteamento Rápido:** Utilização do nikic/fast-route para alta performance em rotas.  
* **Injeção de Dependência:** Configurado via php-di/php-di para um acoplamento fraco e facilidade em testes.  
* **ORM:** Integração com illuminate/database (Eloquent) sem a necessidade do framework Laravel completo.  
* **Segurança Integrada:**  
  * Autenticação via JWT (JSON Web Tokens).  
  * Controle de Tokens Revogados (Blacklist) para logout seguro.  
  * Proteção contra Rate Limiting.  
  * Headers de Segurança automatizados (CORS, X-XSS-Protection, etc).  
* **Respostas Padronizadas:** Utilização de classes Resource para transformar e padronizar os retornos JSON da API.  
* **Tratamento Global de Erros:** Captura centralizada de Exceções (ErrorHandlerMiddleware) traduzindo erros para respostas HTTP adequadas.

## **🛠️ Tecnologias Utilizadas**

* **Linguagem:** PHP 8.1+  
* **Banco de Dados:** MySQL / MariaDB  
* **Roteamento:** FastRoute  
* **ORM:** Laravel Eloquent (illuminate/database)  
* **Autenticação:** Firebase JWT  
* **Container DI:** PHP-DI  
* **Ambiente:** PHP Dotenv (vlucas/phpdotenv)  
* **Testes:** PHPUnit

## **🚀 Como Iniciar (Ambiente de Desenvolvimento)**

### **1\. Pré-requisitos**

* PHP 8.1 ou superior.  
* Composer instalado.  
* Servidor MySQL ou MariaDB rodando.

### **2\. Instalação**

Clone o repositório e instale as dependências:

git clone \[https://github.com/arthur-oliveira-oficial/parrot-php.git\](https://github.com/arthur-oliveira-oficial/parrot-php.git)  
cd parrot-php  
composer install

### **3\. Configuração do Ambiente**

Copie o arquivo de exemplo do .env e configure suas variáveis:

cp .env.example .env

Abra o arquivo .env e configure sua conexão com o banco de dados e a chave secreta do JWT:

APP\_ENV=development  
APP\_DEBUG=true

DB\_HOST=127.0.0.1  
DB\_PORT=3306  
DB\_DATABASE=parrot\_db  
DB\_USERNAME=root  
DB\_PASSWORD=sua\_senha

JWT\_SECRET=sua\_chave\_secreta\_super\_segura

### **4\. Banco de Dados (Migrations e Seeds)**

O projeto possui scripts customizados para gerenciar o banco de dados. Execute os comandos abaixo para criar as tabelas e popular os dados iniciais (incluindo o usuário administrador):

\# Rodar as migrations (criação das tabelas)  
php database/scripts/migrate.php

\# Rodar os seeders (popula o banco com usuário admin inicial)  
php database/scripts/seed.php

### **5\. Executando a Aplicação**

Para fins de desenvolvimento, você pode utilizar o servidor embutido do PHP apontando para a pasta public:

php \-S localhost:8000 \-t public

A API estará disponível em http://localhost:8000.

## **📚 Documentação da API (Endpoints)**

Todas as respostas são em formato application/json.

### **Autenticação**

* POST /api/auth/login \- Autentica um usuário e retorna o token JWT.  
* POST /api/auth/logout \- Revoga o token atual (requer autenticação).

### **Usuários (CRUD)**

*A maioria das rotas de usuários é protegida pelo JwtAuthMiddleware.*

* GET /api/users \- Lista todos os usuários (Paginado).  
* GET /api/users/{id} \- Retorna detalhes de um usuário específico.  
* POST /api/users \- Cria um novo usuário.  
* PUT /api/users/{id} \- Atualiza os dados de um usuário.  
* DELETE /api/users/{id} \- Remove um usuário do sistema.

**Nota sobre Requisições Autenticadas:** \> Para acessar rotas protegidas, envie o header: Authorization: Bearer SEU\_TOKEN\_AQUI.

## **🧪 Testes**

O projeto utiliza o PHPUnit para testes unitários e de integração. Para rodar a suíte de testes (Autenticação, Roteador, CRUD de Usuários):

./vendor/bin/phpunit

*As configurações de teste estão disponíveis no arquivo phpunit.xml.*

## **📁 Estrutura do Projeto**

parrot-php/  
├── config/                \# Configurações do DI Container, Middlewares e Rotas  
├── database/              \# Migrations e Seeders customizados  
│   ├── migrations/  
│   ├── scripts/  
│   └── seed/  
├── docs/                  \# Documentações adicionais (ex: deploy em produção)  
├── public/                \# Document Root, contém o index.php (Front Controller)  
├── src/                   \# Código fonte principal (App)  
│   ├── Controllers/       \# Controladores da API  
│   ├── Core/              \# Núcleo do framework (Router, Request, Response, Capsule)  
│   ├── Exceptions/        \# Exceções HTTP customizadas  
│   ├── Middlewares/       \# Filtros de requisição (Auth, CORS, Rate Limit)  
│   ├── Models/            \# Modelos do Eloquent ORM  
│   └── Views/             \# Resources para formatação de JSON  
├── tests/                 \# Suíte de testes (PHPUnit)  
├── .env.example           \# Exemplo de variáveis de ambiente  
└── composer.json          \# Dependências do projeto

## **🌐 Produção**

Para instruções detalhadas de como fazer o deploy desta aplicação em um ambiente de produção (Nginx/Apache), consulte o arquivo docs/instrucoes-production.txt.

Desenvolvido por Arthur Oliveira.