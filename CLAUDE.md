# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture Overview

This is an e-commerce microservices platform built with Laravel and Docker. The system consists of multiple independent Laravel services orchestrated via Docker Compose with Nginx as a reverse proxy and RabbitMQ for inter-service communication.

### Microservices Structure

The platform follows a domain-driven microservices architecture:

- **api-gateway**: Main entry point and request routing
- **auth-service**: Authentication and authorization (JWT + Laravel Permissions)
- **messages-broker**: Inter-service communication via RabbitMQ
- **addresses-service**: Address management
- **products-service**: Product catalog (planned)
- **baskets-service**: Shopping cart (planned)
- **orders-service**: Order processing (planned)
- **deliveries-service**: Delivery management (planned)
- **newsletters-service**: Email newsletters (planned)
- **sav-service**: Customer service (planned)
- **contacts-service**: Contact management (planned)
- **questions-service**: FAQ system (planned)
- **websites-service**: Website configuration (planned)

### Key Infrastructure

- **Nginx**: Reverse proxy routing requests to services via path-based routing (`/auth/`, `/addresses/`, etc.)
- **RabbitMQ**: Message broker for asynchronous inter-service communication with management UI on port 15672
- **MySQL**: Individual databases per service (auth-db, addresses-db, messages-broker-db)
- **Shared Library**: Common PHP code shared across all services via composer path repository

## Development Commands

### Docker Environment
```bash
# Start all services
docker-compose up -d

# Start with file watching for development
docker-compose up --watch

# View logs
docker-compose logs -f [service-name]

# Execute commands in specific service
docker-compose exec [service-name] bash
```

### Laravel Service Commands
Each service is a standard Laravel application. Execute commands within service containers:

```bash
# Run artisan commands
docker-compose exec auth-service php artisan migrate
docker-compose exec auth-service php artisan make:model User

# Install dependencies
docker-compose exec auth-service composer install

# Run tests
docker-compose exec auth-service php artisan test

# Code formatting (Laravel Pint)
docker-compose exec auth-service ./vendor/bin/pint
```

### Active Services
Currently implemented services with working Laravel installations:
- api-gateway (port 8000)
- auth-service (port 8001) 
- messages-broker (port 8002)
- addresses-service (port 8009)

### Database Access
Each service has its own MySQL database accessible on mapped external ports:
- auth-db: port 3316
- messages-broker-db: port varies (check docker-compose.yml)
- addresses-db: port varies (check docker-compose.yml)

## Project Structure

### Service Organization
```
services/
├── [service-name]/          # Individual Laravel application
│   ├── app/                 # Laravel application code
│   ├── routes/              # Service-specific routes
│   ├── database/            # Migrations and seeders
│   ├── tests/               # PHPUnit tests
│   ├── composer.json        # Service dependencies
│   ├── Dockerfile           # Service container definition
│   └── artisan              # Laravel command line tool
```

### Shared Code
```
shared/                      # Common code library
├── composer.json            # Shared package definition
└── [shared classes]         # Reusable components
```

### Docker Configuration
```
docker/
├── nginx/
│   └── conf.d/
│       └── default.conf     # Nginx routing configuration
```

## Environment Configuration

The `.env` file contains comprehensive configuration for all services including:
- Database connections for each service
- RabbitMQ settings and exchange configuration
- Service URLs and port mappings
- JWT configuration for authentication
- Development tool settings

## Development Workflow

1. **Service Creation**: New services follow the Laravel structure with individual Dockerfiles
2. **Shared Code**: Common functionality goes in the `shared/` directory
3. **Inter-Service Communication**: Use the messages-broker service for async communication
4. **API Design**: Each service exposes REST endpoints routed through Nginx
5. **Testing**: Each service maintains its own PHPUnit test suite

## Git Workflow

The project follows conventional commits with emoji prefixes and specific branch naming:
- Branches: `<type>/#<issue-number>-<description>`
- Commits: `<emoji> <type>(<scope>): <subject>`

Main branches:
- `main`: Production branch
- `dev`: Development branch (current)

## Service Dependencies

Services communicate through:
1. **HTTP requests** via internal Docker network URLs
2. **Message queues** via RabbitMQ for asynchronous operations
3. **Shared library** for common utilities and models

When working on a specific service, ensure you understand its position in the microservices ecosystem and its communication patterns with other services.