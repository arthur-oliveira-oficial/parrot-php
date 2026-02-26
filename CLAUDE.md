# Parrot PHP Framework

A custom micro-framework PHP for building REST APIs, built with Laravel components and PSR-compliant libraries.

## Project Overview

- **Type**: Micro-framework PHP for REST APIs
- **PHP Version**: 8.4+
- **License**: MIT

## Tech Stack

| Component | Library |
|-----------|---------|
| Routing | FastRoute (nikic/fast-route) |
| ORM | Laravel Eloquent (illuminate/database) |
| Container | PHP-DI (php-di/php-di) |
| HTTP Messages | PSR-7 (nyholm/psr7) |
| Server Request | nyholm/psr7-server |
| Environment | vlucas/phpdotenv |

## Directory Structure

```
/var/www/html/parrot-php/
├── public/                  # Web root - entry point
│   └── index.php           # Front controller
├── src/
│   ├── Core/               # Framework core
│   │   ├── Application.php
│   │   ├── Router.php
│   │   ├── FastRouteRouter.php
│   │   ├── DatabaseCapsule.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── MiddlewareQueue.php
│   ├── Controllers/        # HTTP Controllers
│   │   ├── Controller.php
│   │   ├── AuthController.php
│   │   └── UserController.php
│   ├── Models/              # Eloquent Models
│   │   ├── Model.php
│   │   ├── EloquentModel.php
│   │   └── UserModel.php
│   ├── Middlewares/         # HTTP Middlewares (PSR-15)
│   │   ├── CorsMiddleware.php
│   │   ├── JwtAuthMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   ├── SecurityHeadersMiddleware.php
│   │   └── ErrorHandlerMiddleware.php
│   ├── Views/               # JSON Response Formatters
│   │   ├── Resource.php
│   │   └── UserResource.php
│   └── Exceptions/          # HTTP Exceptions
│       ├── HttpException.php
│       ├── NotFoundException.php
│       ├── UnauthorizedException.php
│       ├── ForbiddenException.php
│       ├── BadRequestException.php
│       └── MethodNotAllowedException.php
├── config/
│   ├── routes.php           # Route definitions
│   ├── container.php        # PHP-DI configuration
│   └── middlewares.php       # Global middleware stack
├── database/
│   ├── migrations/         # Database migrations
│   └── seeds/              # Database seeds
├── tests/                   # PHPUnit tests
├── .env                     # Environment variables
└── composer.json            # Dependencies
```

## API Endpoints

### Authentication
| Method | Endpoint | Middleware | Description |
|--------|----------|------------|-------------|
| POST | `/api/auth/login` | None | User login |
| POST | `/api/auth/logout` | None | User logout |
| GET | `/api/auth/me` | JWT | Get current user |

### Users (CRUD)
| Method | Endpoint | Middleware | Description |
|--------|----------|------------|-------------|
| GET | `/api/usuarios` | JWT | List all users |
| GET | `/api/usuarios/{id}` | JWT | Get user by ID |
| POST | `/api/usuarios` | JWT | Create new user |
| PUT | `/api/usuarios/{id}` | JWT | Update user |
| DELETE | `/api/usuarios/{id}` | JWT | Delete user |

## Configuration (.env)

```bash
# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (MySQL/MariaDB)
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=parrot_db
DB_USER=root
DB_PASSWORD=your_password

# JWT Authentication
JWT_SECRET=your-secret-key
JWT_EXPIRY=3600

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000

# Rate Limiting
RATE_LIMIT_MAX_REQUESTS=60
RATE_LIMIT_WINDOW_SECONDS=60
```

## Dependencies Injection

The framework uses PHP-DI for dependency injection. Configuration is in `config/container.php`.

Key services:
- `App\Core\Router` - Route dispatcher
- `App\Core\DatabaseCapsule` - Eloquent database connection
- `Psr\Http\Message\ServerRequestInterface` - Current HTTP request

## Running the Project

### Start Development Server
```bash
php -S localhost:8000 -t public/
```

### Run Tests
```bash
vendor/bin/phpunit
```

## Architecture Notes

### Request Flow
1. Request hits `public/index.php`
2. Environment variables loaded via phpdotenv
3. PHP-DI container is configured
4. Global middlewares execute (ErrorHandler, Security, RateLimit, CORS)
5. Router dispatches to controller
6. Controller returns response

### Middleware Stack (Order)
1. ErrorHandlerMiddleware - Catches all exceptions
2. SecurityHeadersMiddleware - Adds security headers
3. RateLimitMiddleware - Rate limiting
4. CorsMiddleware - CORS headers

### Adding New Routes
Edit `config/routes.php`:
```php
return [
    'METHOD /path' => [Controller::class, 'method'],
    'METHOD /path' => [Controller::class, 'method', Middleware::class],
];
```

### Creating New Controllers
Extend `App\Controllers\Controller` base class and use `json()` helper method.

### Creating New Models
Extend `App\Models\EloquentModel` for Eloquent models with soft deletes, or `App\Models\Model` for base functionality.
