# Documentação de Arquitetura do Framework Parrot PHP

Este documento apresenta a arquitetura completa do framework Parrot PHP através de diagramas visuais que facilitam o entendimento da estrutura e fluxo do sistema.

---

## 1. Diagrama de Arquitetura Geral

O diagrama abaixo apresenta a visão geral dos principais componentes do framework Parrot PHP, desde o ponto de entrada até a camada de visualização.

```mermaid
flowchart TB
    subgraph PUBLIC["Pública (public/)"]
        INDEX["index.php<br/>Bootstrap"]
    end

    subgraph CORE["Core"]
        APP["Application"]
        ROUTER["Router<br/>(FastRouteRouter)"]
        DBCAPS["DatabaseCapsule"]
        MIDQ["MiddlewareQueue"]
    end

    subgraph CONTROLLERS["Controllers"]
        BASE["Controller<br/>(Base)"]
        AUTH["AuthController"]
        USER["UserController"]
    end

    subgraph MODELS["Models"]
        MBASE["Model"]
        ELOQ["EloquentModel"]
        UM["UserModel"]
        TKREV["TokenRevogado"]
    end

    subgraph MIDDLEWARES["Middlewares"]
        ERR["ErrorHandler"]
        CORS["Cors"]
        JWT["JwtAuth"]
        RATE["RateLimit"]
        SEC["SecurityHeaders"]
    end

    subgraph VIEWS["Views/Resources"]
        RES["Resource"]
        URES["UserResource"]
    end

    subgraph EXCEPTIONS["Exceptions"]
        HTTP["HttpException"]
        BAD["BadRequestException"]
        UNAUTH["UnauthorizedException"]
        NOTFOUND["NotFoundException"]
        FORBIDDEN["ForbiddenException"]
        METHOD["MethodNotAllowedException"]
    end

    INDEX --> APP
    APP --> MIDQ
    MIDQ --> ROUTER
    ROUTER --> BASE
    BASE --> AUTH
    BASE --> USER
    AUTH --> MBASE
    USER --> MBASE
    MBASE --> ELOQ
    ELOQ --> UM
    ELOQ --> TKREV
    RES --> URES

    style PUBLIC fill:#e3f2fd,stroke:#1976d2
    style CORE fill:#fff3e0,stroke:#f57c00
    style CONTROLLERS fill:#e8f5e9,stroke:#388e3c
    style MODELS fill:#fce4ec,stroke:#c2185b
    style MIDDLEWARES fill:#f3e5f5,stroke:#7b1fa2
    style VIEWS fill:#e0f2f1,stroke:#00796b
    style EXCEPTIONS fill:#ffebee,stroke:#d32f2f
```

---

## 2. Diagrama de Fluxo de Requisição (Request Lifecycle)

Este diagrama ilustra o ciclo de vida de uma requisição HTTP no framework, demonstrando o padrão Onion/Pipeline de middlewares.

```mermaid
flowchart LR
    subgraph CLIENT["Cliente"]
        HTTP["Requisição HTTP"]
    end

    subgraph ENTRADA["Ponto de Entrada"]
        BOOT["public/index.php<br/>Bootstrap"]
    end

    subgraph PIPELINE["Pipeline de Middlewares"]
        direction TB
        M1["MiddlewareQueue"]
        M2["ErrorHandler"]
        M3["SecurityHeaders"]
        M4["Cors"]
        M5["RateLimit"]
        M6["JwtAuth"]
    end

    subgraph ROTEAMENTO["Roteamento"]
        R1["Router<br/>FastRoute"]
        R2["Match Route"]
    end

    subgraph EXECUCAO["Execução"]
        C1["Controller"]
        C2["Action Method"]
        C3["Model/Service"]
    end

    subgraph RESPOSTA["Resposta"]
        RESP["Response"]
        JSON["JSON Response"]
    end

    HTTP --> BOOT
    BOOT --> M1
    M1 --> M2 --> M3 --> M4 --> M5 --> M6
    M6 --> R1
    R1 --> R2
    R2 --> C1
    C1 --> C2
    C2 --> C3
    C3 --> RESP
    RESP --> JSON

    style CLIENT fill:#bbdefb,stroke:#1565c0
    style ENTRADA fill:#ffe0b2,stroke:#ef6c00
    style PIPELINE fill:#e1bee7,stroke:#7b1fa2
    style ROTEAMENTO fill:#c8e6c9,stroke:#2e7d32
    style EXECUCAO fill:#ffccbc,stroke:#d84315
    style RESPOSTA fill:#b2dfdb,stroke:#00695c
```

### Descrição do Fluxo

1. **Requisição HTTP**: O cliente envia uma requisição para o servidor
2. **Bootstrap**: O arquivo `public/index.php` inicializa a aplicação
3. **Pipeline de Middlewares**: A requisição passa por uma sequência de middlewares
   - `ErrorHandler`: Tratamento de erros
   - `SecurityHeaders`: Cabeçalhos de segurança
   - `Cors`: Controle de acesso cruzado
   - `RateLimit`: Limite de requisições
   - `JwtAuth`: Autenticação JWT
4. **Roteamento**: O Router identifica a rota correspondente
5. **Controller**: O método adequado do controller é executado
6. **Model**: Dados são manipulados através dos models
7. **Response**: A resposta é retornada ao cliente

---

## 3. Diagrama de Hierarquia de Classes

### 3.1 Hierarquia de Controllers

```mermaid
classDiagram
    class Controller {
        +request: Request
        +response: Response
        +json(data, statusCode)
        +render(view, data)
    }

    class AuthController {
        +login()
        +logout()
        +me()
    }

    class UserController {
        +index()
        +show(id)
        +store()
        +update(id)
        +destroy(id)
    }

    Controller <|-- AuthController
    Controller <|-- UserController
```

### 3.2 Hierarquia de Models

```mermaid
classDiagram
    class Model {
        +table: string
        +fillable: array
        +hidden: array
        +find(id)
        +all()
        +create(data)
        +update(id, data)
        +delete(id)
        +where(column, value)
    }

    class EloquentModel {
        +connection: PDO
        +query(sql, params)
        +beginTransaction()
        +commit()
        +rollback()
    }

    class UserModel {
        +table: string = 'usuarios'
    }

    class TokenRevogado {
        +table: string = 'tokens_revogados'
        +isRevogado(token)
        +revogar(token)
    }

    Model <|-- EloquentModel
    EloquentModel <|-- UserModel
    EloquentModel <|-- TokenRevogado
```

### 3.3 Hierarquia de Exceptions

```mermaid
classDiagram
    class HttpException {
        +message: string
        +statusCode: int
        +__construct(message, statusCode)
    }

    class BadRequestException {
        +__construct(message = 'Bad Request')
    }

    class UnauthorizedException {
        +__construct(message = 'Unauthorized')
    }

    class ForbiddenException {
        +__construct(message = 'Forbidden')
    }

    class NotFoundException {
        +__construct(message = 'Not Found')
    }

    class MethodNotAllowedException {
        +__construct(message = 'Method Not Allowed')
    }

    HttpException <|-- BadRequestException
    HttpException <|-- UnauthorizedException
    HttpException <|-- ForbiddenException
    HttpException <|-- NotFoundException
    HttpException <|-- MethodNotAllowedException
```

### 3.4 Hierarquia de Resources (Views)

```mermaid
classDiagram
    class Resource {
        +toArray(): array
        +collection(items): array
    }

    class UserResource {
        +id: int
        +nome: string
        +email: string
        +toArray()
    }

    Resource <|-- UserResource
```

---

## 4. Diagrama de Estrutura de Diretórios

Este diagrama apresenta a organização completa dos diretórios e arquivos do framework Parrot PHP.

```mermaid
graph TD
    ROOT["parrot-php/"]
    
    CONFIG["config/"]
    CONFIG <|-- CONTAINER["container.php"]
    CONFIG <|-- MIDDLEWARES["middlewares.php"]
    CONFIG <|-- ROUTES["routes.php"]
    
    PUBLIC["public/"]
    PUBLIC <|-- INDEX["index.php"]
    
    SRC["src/"]
    
    SRC <|-- CORE["Core/"]
    CORE <|-- APP["Application.php"]
    CORE <|-- REQUEST["Request.php"]
    CORE <|-- RESPONSE["Response.php"]
    CORE <|-- ROUTER["Router.php"]
    CORE <|-- FASTROUTER["FastRouteRouter.php"]
    CORE <|-- DBCAPS["DatabaseCapsule.php"]
    CORE <|-- MIDQ["MiddlewareQueue.php"]
    
    SRC <|-- CONTROLLERS["Controllers/"]
    CONTROLLERS <|-- BASEC["Controller.php"]
    CONTROLLERS <|-- AUTH["AuthController.php"]
    CONTROLLERS <|-- USER["UserController.php"]
    
    SRC <|-- MODELS["Models/"]
    MODELS <|-- MODEL["Model.php"]
    MODELS <|-- ELOQ["EloquentModel.php"]
    MODELS <|-- USERM["UserModel.php"]
    MODELS <|-- TKREV["TokenRevogado.php"]
    
    SRC <|-- MIDDLEWARES["Middlewares/"]
    MIDDLEWARES <|-- ERR["ErrorHandlerMiddleware.php"]
    MIDDLEWARES <|-- CORS["CorsMiddleware.php"]
    MIDDLEWARES <|-- JWT["JwtAuthMiddleware.php"]
    MIDDLEWARES <|-- RATE["RateLimitMiddleware.php"]
    MIDDLEWARES <|-- SEC["SecurityHeadersMiddleware.php"]
    
    SRC <|-- EXCEPTIONS["Exceptions/"]
    EXCEPTIONS <|-- HTTP["HttpException.php"]
    EXCEPTIONS <|-- BAD["BadRequestException.php"]
    EXCEPTIONS <|-- UNAUTH["UnauthorizedException.php"]
    EXCEPTIONS <|-- NOTFOUND["NotFoundException.php"]
    EXCEPTIONS <|-- FORBIDDEN["ForbiddenException.php"]
    EXCEPTIONS <|-- METHOD["MethodNotAllowedException.php"]
    
    SRC <|-- VIEWS["Views/"]
    VIEWS <|-- RES["Resource.php"]
    VIEWS <|-- URES["UserResource.php"]
    
    DATABASE["database/"]
    DATABASE <|-- MIGRATIONS["migrations/"]
    DATABASE <|-- SEED["seed/"]
    DATABASE <|-- SCRIPTS["scripts/"]
    
    TESTS["tests/"]
    TESTS <|-- BOOTSTRAP["bootstrap.php"]
    TESTS <|-- TESTCASE["TestCase.php"]
    TESTS <|-- AUTHTEST["AuthTest.php"]
    TESTS <|-- USERCRUD["UserCrudTest.php"]
    
    DOCS["docs/"]
    DOCS <|-- INSTALACAO["instalacao.md"]
    
    style ROOT fill:#f5f5f5,stroke:#333
    style CONFIG fill:#fff3e0,stroke:#f57c00
    style PUBLIC fill:#e3f2fd,stroke:#1976d2
    style SRC fill:#fff8e1,stroke:#ffc107
    style CORE fill:#e8f5e9,stroke:#388e3c
    style CONTROLLERS fill:#e8f5e9,stroke:#388e3c
    style MODELS fill:#e8f5e9,stroke:#388e3c
    style MIDDLEWARES fill:#f3e5f5,stroke:#7b1fa2
    style EXCEPTIONS fill:#ffebee,stroke:#d32f2f
    style VIEWS fill:#e0f2f1,stroke:#00796b
    style DATABASE fill:#e0f7fa,stroke:#0097a7
    style TESTS fill:#fce4ec,stroke:#c2185b
    style DOCS fill:#e8eaf6,stroke:#3f51b5
```

---

## 5. Resumo dos Componentes

| Componente | Descrição | Localização |
|------------|-----------|-------------|
| **Application** | Classe principal que coordena todos os componentes | `src/Core/Application.php` |
| **Router** | Sistema de roteamento baseado em FastRoute | `src/Core/FastRouteRouter.php` |
| **DatabaseCapsule** | Gerenciador de conexão com banco de dados | `src/Core/DatabaseCapsule.php` |
| **MiddlewareQueue** | Fila de execução de middlewares | `src/Core/MiddlewareQueue.php` |
| **Controller** | Classe base para todos os controllers | `src/Controllers/Controller.php` |
| **Model** | Classe base para modelos de dados | `src/Models/Model.php` |
| **EloquentModel** | Implementação do padrão Active Record | `src/Models/EloquentModel.php` |
| **HttpException** | Classe base para exceções HTTP | `src/Exceptions/HttpException.php` |
| **Resource** | Classe base para transformação de dados | `src/Views/Resource.php` |

---

## 6. Padrões de Projeto Utilizados

O framework Parrot PHP utiliza os seguintes padrões de projeto:

- **MVC (Model-View-Controller)**: Separação clara entre dados, lógica de negócio e interface
- **Active Record**: Implementado no EloquentModel para manipulação de dados
- **Pipeline/Onion Pattern**: Para o processamento de middlewares
- **Dependency Injection**: Através do container de serviços
- **Factory**: Para criação de exceptions específicas

---

*Documentação gerada para o Framework Parrot PHP*
