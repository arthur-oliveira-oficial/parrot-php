# Parrot PHP Framework

Parrot PHP is a modern micro-framework for building REST APIs in PHP 8.4+, built with Laravel components and PSR-compliant libraries.

## Project Overview

- **Type**: REST API Micro-framework
- **PHP Version**: 8.4+
- **Standards**: PSR-7 (HTTP Messages), PSR-15 (HTTP Middlewares)

## Project Structure

```
parrot-php/
├── public/
│   └── index.php              # Front controller entry point
├── src/
│   ├── Core/
│   │   ├── Application.php    # Main orchestrator (PSR-15 RequestHandler)
│   │   ├── FastRouteRouter.php # FastRoute-based router
│   │   ├── Router.php         # Simple custom router (deprecated)
│   │   ├── MiddlewareQueue.php # Middleware pipeline (Onion Pattern)
│   │   ├── Response.php       # PSR-7 response helpers
│   │   ├── Request.php        # PSR-7 request helpers
│   │   └── DatabaseCapsule.php # Laravel Eloquent wrapper
│   ├── Controllers/
│   │   ├── Controller.php     # Abstract base controller
│   │   ├── AuthController.php # Authentication (login, logout, me)
│   │   └── UserController.php  # User CRUD operations
│   ├── Models/
│   │   ├── Model.php          # Base PDO model
│   │   ├── EloquentModel.php  # Laravel Eloquent base
│   │   ├── UserModel.php      # User model with SoftDeletes
│   │   └── TokenRevogado.php # Revoked JWT tokens (blacklist)
│   ├── Middlewares/
│   │   ├── ErrorHandlerMiddleware.php
│   │   ├── JwtAuthMiddleware.php
│   │   ├── CorsMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   └── SecurityHeadersMiddleware.php
│   ├── Exceptions/
│   │   ├── HttpException.php
│   │   ├── NotFoundException.php
│   │   ├── UnauthorizedException.php
│   │   ├── ForbiddenException.php
│   │   ├── BadRequestException.php
│   │   └── MethodNotAllowedException.php
│   └── Views/
│       ├── Resource.php       # Base API resource/transformer
│       └── UserResource.php   # User response transformer
├── config/
│   ├── routes.php            # API route definitions
│   ├── middlewares.php       # Global middleware stack
│   └── container.php         # PHP-DI dependency definitions
├── tests/                    # Unit tests
└── .env                     # Environment configuration
```

## API Endpoints

| Method | Endpoint | Handler | Auth |
|--------|----------|---------|------|
| POST | /api/auth/login | AuthController::login | Public |
| POST | /api/auth/logout | AuthController::logout | Public |
| GET | /api/auth/me | AuthController::me | JWT |
| GET | /api/usuarios | UserController::index | JWT (Admin only) |
| GET | /api/usuarios/{id} | UserController::show | JWT |
| POST | /api/usuarios | UserController::store | JWT |
| PUT | /api/usuarios/{id} | UserController::update | JWT |
| DELETE | /api/usuarios/{id} | UserController::destroy | JWT |

## Useful Commands

```bash
# Install dependencies
composer install

# Start development server
php -S localhost:8000 -t public

# Run tests (if available)
./vendor/bin/phpunit
```

## Code Patterns

### Controllers
Extend `Controller` base class which provides:
- `$this->success($data, $statusCode)` - Return JSON success
- `$this->error($message, $statusCode)` - Return JSON error
- `$this->forbidden($message)` - Return 403 Forbidden
- `$this->getParam($name)` - Get route parameter
- `$this->getBody()` - Get request body
- `$this->getUserId()` - Get authenticated user ID from JWT
- `$this->getUserType()` - Get authenticated user type (admin/user)
- `$this->validate($rules)` - Validate request data

### Models
- Use Laravel Eloquent ORM with SoftDeletes trait
- Table: `usuarios` (users)
- Fields: id, nome, email, senha, tipo, created_at, updated_at, deletado_em
- Token revocation: Table `tokens_revogados` stores revoked JWT tokens (jti, expiracao)

### Authorization
- UserController uses `canAccessUser($userId)` method to enforce ownership or admin privileges
- Only admin users can list all users (`GET /api/usuarios`)
- Non-admin users can only access their own profile (show, update, delete)

### Exceptions
Throw custom exceptions for HTTP errors:
- `throw new NotFoundException('message')` - 404
- `throw new UnauthorizedException('message')` - 401
- `throw new ForbiddenException('message')` - 403
- `throw new BadRequestException('message')` - 400

### Responses
Use Resource classes to transform responses:
- `new UserResource($user)` - Transform user model
- `new UserResource($user)->toArray()`

## Dependencies

- **nikic/fast-route** - Routing
- **illuminate/database** - Laravel Eloquent ORM
- **nyholm/psr7** / **nyholm/psr7-server** - PSR-7 HTTP messages
- **php-di/php-di** - Dependency injection
- **vlucas/phpdotenv** - Environment variables
- **firebase/php-jwt** - JWT authentication

## Environment Variables (.env)

```
APP_ENV=development
APP_DEBUG=true
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=parrot_db
DB_USER=root
DB_PASSWORD=***
JWT_SECRET=***
JWT_EXPIRY=3600
CORS_ALLOWED_ORIGINS=http://localhost:3000
RATE_LIMIT_MAX_REQUESTS=60
RATE_LIMIT_WINDOW_SECONDS=60
```
