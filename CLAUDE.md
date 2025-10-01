# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture Overview

This is a Laravel-based e-commerce microservices platform with fully asynchronous communication. The architecture follows event-driven patterns with RabbitMQ message broker, distributed object storage via MinIO, and database-per-service isolation.

### Communication Flow
```
Client → Nginx (80/443) → API Gateway → RabbitMQ → Microservices
                                             ↓
                                      MinIO Storage
```

All requests route through the API Gateway which coordinates service communication via RabbitMQ queues and exchanges. This enables true asynchronous processing and service decoupling.

### Active Microservices

All services are fully implemented Laravel applications with individual databases:

- **api-gateway** (port 8100) - Single entry point, request routing, service orchestration
- **auth-service** - JWT authentication, role-based permissions (Spatie Laravel Permission)
- **messages-broker** - RabbitMQ message handling, inter-service communication coordination
- **addresses-service** - Address management and validation
- **products-service** - Product catalog, inventory
- **baskets-service** - Shopping cart management
- **orders-service** - Order processing and lifecycle
- **deliveries-service** - Delivery tracking and management
- **newsletters-service** - Email campaigns and subscriptions
- **sav-service** (SAV = Customer Service) - Support ticket management
- **contacts-service** - Contact form handling
- **websites-service** - Multi-site configuration
- **questions-service** - FAQ system

### Infrastructure Components

**Message Broker**
- RabbitMQ (ports 5672, 15672 for management UI)
- Each service has dedicated exchanges and queues
- Management UI: http://localhost:15672 (guest/guest)

**Object Storage (MinIO)**
- MinIO distributed object storage (ports 9000 API, 9001 Console)
- S3-compatible API with AWS SDK
- 3 buckets: products (images), sav (attachments), newsletters (templates)
- Presigned URLs for secure temporary access
- Console: http://localhost:9001 (admin/adminpass123)
- Documentation: docs/minio/MINIO.md

**Reverse Proxy**
- Nginx routes all traffic to API Gateway
- `/api/` and `/v1/` paths handled by gateway
- Custom headers: X-Request-ID for tracing

**Databases**
- MySQL 8.0 per service (database-per-service pattern)
- Each service has isolated database with external ports for debugging
- See `.env` for complete port mappings

## Development Environment

### Prerequisites
- Docker and Docker Compose
- Make (for simplified commands)
- Optional: kubectl and helm for Kubernetes deployment

### Quick Start

```bash
# First time setup - builds images, starts services, runs migrations
make docker-install

# Daily development - start with file watching
make dev
# OR
docker-compose up --watch

# Check service health
make health-docker

# View all services status
make docker-status
```

### Common Make Commands

**Docker Operations**
```bash
make docker-start          # Start all services
make docker-stop           # Stop services (preserves data)
make docker-down           # Stop and remove containers
make docker-clean          # Complete cleanup (removes volumes)
make docker-endpoints      # Show service URLs
make stats                 # Resource usage
```

**MinIO Object Storage**
```bash
make minio-workflow        # Complete workflow (start + setup + validate + test)
make minio-start           # Start MinIO container
make minio-setup           # Create buckets (products, sav, newsletters)
make minio-health          # Health check
make minio-console         # Open web console (http://localhost:9001)
make minio-validate        # Validate Phase 1 (26 checks)
make minio-test            # Integration tests (23 tests)
make minio-clean           # Clean buckets
make minio-stop            # Stop MinIO
```

**Database Management**
```bash
make migrate-all           # Run migrations on all services
make seed-all              # Run seeders on all services
make fresh-all             # Fresh migration with seeds (destructive)
make backup-docker         # Backup all databases to ./backups/
```

**Development Utilities**
```bash
make shell SERVICE_NAME=auth-service     # Access service shell
make composer-install SERVICE_NAME=auth-service
make clear-cache                         # Clear Laravel caches
```

**Testing**
```bash
make test-docker           # Run tests on all services
make test-service SERVICE_NAME=auth-service
make test-all              # Complete test suite (Docker + integration)
```

**Kubernetes (Production)**
```bash
make k8s-deploy            # Deploy to Kubernetes
make k8s-status            # Check deployment status
make k8s-monitoring        # Open monitoring dashboards
make k8s-health            # Health checks
```

### Working with Individual Services

Each service is a standalone Laravel application. Execute commands within service containers:

```bash
# Access service shell
docker-compose exec auth-service bash

# Run artisan commands
docker-compose exec auth-service php artisan migrate
docker-compose exec auth-service php artisan make:controller UserController
docker-compose exec auth-service php artisan db:seed
docker-compose exec auth-service php artisan queue:work

# Run tests in specific service
docker-compose exec auth-service php artisan test
docker-compose exec auth-service php artisan test --filter UserTest

# Code formatting with Laravel Pint
docker-compose exec auth-service ./vendor/bin/pint
docker-compose exec auth-service ./vendor/bin/pint --test  # Check without fixing

# Composer operations
docker-compose exec auth-service composer install
docker-compose exec auth-service composer update
docker-compose exec auth-service composer require package/name
```

### Technology Stack

**Framework & Language**
- PHP 8.3+
- Laravel 12
- Composer dependency management

**Authentication & Authorization**
- JWT tokens via tymon/jwt-auth
- Role-based permissions via Spatie Laravel Permission
- Centralized auth through auth-service

**Testing & Quality**
- PHPUnit for unit/feature tests
- Laravel Pint for code formatting (PSR-12)
- Postman collections for API testing (see `docs/api/postman/`)

**Message Queue**
- php-amqplib/php-amqplib for RabbitMQ integration
- Async processing via Laravel queues

## Project Structure

### Service Organization
```
services/
├── [service-name]/
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Middleware/
│   │   │   └── Requests/
│   │   ├── Models/
│   │   ├── Services/        # Business logic layer
│   │   └── Components/      # Reusable components
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeders/
│   │   └── factories/
│   ├── routes/
│   │   ├── api.php          # API routes
│   │   └── web.php
│   ├── tests/
│   │   ├── Feature/
│   │   └── Unit/
│   ├── composer.json        # Service dependencies
│   ├── Dockerfile
│   └── .env
```

### Shared Library

Common code is shared via a composer path repository in `shared/`:

```
shared/
├── Components/       # Shared components
├── Exceptions/       # Common exceptions
├── Interfaces/       # Service contracts
├── Middleware/       # Reusable middleware
├── Models/          # Shared models
├── Services/        # Shared business logic
└── composer.json
```

Services import shared code in composer.json:
```json
{
  "require": {
    "e-commerce/shared": "@dev"
  },
  "repositories": [
    {
      "type": "path",
      "url": "../../shared",
      "options": { "symlink": true }
    }
  ]
}
```

Access in code: `use Shared\Services\ClassName;`

### Docker Configuration
```
docker/
└── nginx/
    └── conf.d/
        └── default.conf     # Nginx routing (all → API Gateway)
```

## API Testing

Comprehensive Postman collections in `docs/api/postman/`:
- Complete E-commerce API v2 collection
- Environment files (Development, Staging, Production)
- Automated tests and validation scripts

```bash
# Validate Postman collection
cd docs/api/postman
./validate-collection.sh
```

## Inter-Service Communication

Services communicate via RabbitMQ exchanges and queues:

1. **API Gateway receives HTTP request**
2. **Gateway publishes message to RabbitMQ exchange**
3. **Target service consumes from its queue**
4. **Service processes and publishes response**
5. **Gateway receives response and returns to client**

Each service configures RabbitMQ connection via environment:
```bash
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=service_exchange
RABBITMQ_QUEUE=service_queue
```

## Git Workflow

### Branch Strategy
- `main` - Production branch
- `dev` - Development branch (current working branch)
- Feature branches: `<type>/#<issue>-<description>`

Examples:
- `feat/#8-addresses-service`
- `fix/#15-authentication-bug`
- `refactor/#23-clean-project`

### Commit Convention

Follow Gitmoji + Conventional Commits:
```
<emoji> <type>(<scope>): <subject>
```

**Common Types**: feat, fix, refactor, docs, test, chore, perf, ci

**Common Emojis** (via Gitmoji):
- ✨ feat - New feature
- 🐛 fix - Bug fix
- ♻️ refactor - Code refactoring
- 📝 docs - Documentation
- 🔧 config - Configuration changes
- 🏗️ feat - Architecture changes
- 🔐 feat - Security/auth features

Examples:
```
✨ feat(auth): add JWT token refresh endpoint
🐛 fix(orders): resolve race condition in order processing
♻️ refactor(products): clean up product service architecture
🔧 config(docker): update nginx routing configuration
```

## Environment Configuration

The `.env` file contains comprehensive configuration for all services:
- Database connections per service with external ports for debugging
- RabbitMQ configuration and credentials
- Service URLs for inter-service communication
- JWT configuration
- MinIO configuration

Each service also has its own `.env` file inheriting from the root configuration.

## Kubernetes Deployment

The platform supports progressive migration to Kubernetes with full automation:

```bash
# Complete installation (Docker + K8s ready)
make install-complete

# Kubernetes setup
make k8s-setup

# Deploy to environment (development, staging, production)
make K8S_ENVIRONMENT=staging k8s-deploy

# Migration from Docker to Kubernetes
make migrate-to-k8s
```

Kubernetes manifests in `k8s/`, Helm charts in `helm/`, automation scripts in `scripts/`.

## Documentation

Comprehensive documentation in `docs/`:
- `docs/api/` - Postman collections and API documentation
- `docs/architecture/` - Architecture plans and technical design
- `docs/deployment/` - Docker and Kubernetes deployment guides
- `docs/development/` - Development guides and contributing
- `docs/maintenance/` - Automation and maintenance scripts

See `docs/README.md` for complete navigation.

## Troubleshooting

**Services won't start**
```bash
# Check logs
docker-compose logs -f [service-name]

# Rebuild images
docker-compose build --no-cache [service-name]
```

**Database connection issues**
```bash
# Check database is running
docker-compose ps

# Wait for databases to be ready
sleep 15  # After docker-compose up

# Reset databases
make fresh-all
```

**RabbitMQ connection failed**
- Verify RabbitMQ is running: `docker-compose ps rabbitmq`
- Check management UI: http://localhost:15672
- Review service environment variables for correct RabbitMQ config

**Port conflicts**
- Check `.env` for port mappings
- Ensure no other services using ports 80, 443, 5672, 15672, 9000, 9001
- Modify external ports in `.env` if needed

## Performance Targets

- Response Time: < 500ms for API calls
- Throughput: 1000 req/s per service
- Availability: 99.9% uptime
- Auto-scaling enabled on Kubernetes
