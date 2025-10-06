# Development Guide

## Setup Initial

### Prérequis

- Docker & Docker Compose
- Make
- Git
- PHP 8.3+ (optionnel pour local)

### Installation

```bash
# Clone repository
git clone <repo-url>
cd e-commerce-back

# Installation complète
make install-complete

# Ou Docker uniquement
make docker-install
```

### Configuration

Copier et configurer `.env`:
```bash
cp .env.example .env
# Modifier selon environnement
```

## Workflow Development

### Démarrage quotidien

```bash
# Démarrer services
make docker-start

# Vérifier santé
make health-docker

# Développer...

# Arrêter
make docker-stop
```

### Développement avec watch mode

```bash
make dev
# Auto-reload sur changements fichiers
```

## Créer un Nouveau Service

### 1. Structure

```bash
services/
└── mon-service/
    ├── app/
    │   ├── Http/Controllers/
    │   ├── Models/
    │   └── Services/
    ├── database/
    │   ├── migrations/
    │   └── seeders/
    ├── routes/
    │   └── api.php
    ├── tests/
    ├── composer.json
    ├── Dockerfile
    └── .env
```

### 2. Configuration Docker

Ajouter dans `docker-compose.yml`:
```yaml
mon-service:
  build: ./services/mon-service
  ports:
    - "8013:8000"
  environment:
    - DB_HOST=mon-service-db
  depends_on:
    - mon-service-db
    - rabbitmq
```

### 3. Database

```yaml
mon-service-db:
  image: mysql:8.0
  environment:
    MYSQL_DATABASE: mon_service_db
    MYSQL_ROOT_PASSWORD: root
```

### 4. Shared Code

Utiliser le package partagé:
```json
{
  "require": {
    "e-commerce/shared": "@dev"
  },
  "repositories": [{
    "type": "path",
    "url": "../../shared"
  }]
}
```

## Tests

### Exécuter tests

```bash
# Tous services
make test-docker

# Service spécifique
make test-service SERVICE_NAME=mon-service

# Avec coverage
docker-compose exec mon-service php artisan test --coverage
```

### Écrire tests

```php
// tests/Feature/MyTest.php
namespace Tests\Feature;

use Tests\TestCase;

class MyTest extends TestCase
{
    public function test_example()
    {
        $response = $this->get('/api/endpoint');
        $response->assertStatus(200);
    }
}
```

## Code Quality

### Laravel Pint (PSR-12)

```bash
# Formater code
docker-compose exec mon-service ./vendor/bin/pint

# Vérifier sans modifier
docker-compose exec mon-service ./vendor/bin/pint --test
```

### Standards

- PSR-12 coding style
- Meaningful variable names
- Single responsibility
- DRY principle

## Database

### Migrations

```bash
# Créer migration
docker-compose exec mon-service php artisan make:migration create_table

# Exécuter migrations
make migrate-all

# Rollback
docker-compose exec mon-service php artisan migrate:rollback
```

### Seeders

```bash
# Créer seeder
docker-compose exec mon-service php artisan make:seeder MySeeder

# Exécuter seeders
make seed-all
```

## API Development

### Tester API

Collections Postman disponibles:
```bash
docs/api/postman/
├── Complete E-commerce API v2.postman_collection.json
├── Development Environment.postman_environment.json
└── validate-collection.sh
```

### Endpoints Standards

```php
// routes/api.php
Route::middleware('auth:api')->group(function () {
    Route::get('/items', [ItemController::class, 'index']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::get('/items/{id}', [ItemController::class, 'show']);
    Route::put('/items/{id}', [ItemController::class, 'update']);
    Route::delete('/items/{id}', [ItemController::class, 'destroy']);
});
```

## RabbitMQ Integration

### Publisher

```php
use Shared\Services\RabbitMQService;

$rabbitMQ = new RabbitMQService();
$rabbitMQ->publish('exchange', 'routing.key', ['data' => 'value']);
```

### Consumer

```php
$rabbitMQ->consume('queue', function ($message) {
    // Process message
    $data = json_decode($message->body, true);
});
```

## MinIO Integration

### Upload fichiers

```php
use Shared\Services\MinioService;

$minio = new MinioService('bucket-name');
$result = $minio->uploadFile('path/file.jpg', $file);
```

## Debugging

### Logs

```bash
# Logs service
docker-compose logs -f mon-service

# Logs tous services
docker-compose logs -f
```

### Shell access

```bash
# Accéder au container
make shell SERVICE_NAME=mon-service

# Ou directement
docker-compose exec mon-service bash
```

### Database access

```bash
# MySQL CLI
docker-compose exec mon-service-db mysql -u root -proot mon_service_db
```

## Git Workflow

### Branches

```bash
# Nouvelle feature
git checkout -b feat/#123-feature-name

# Bug fix
git checkout -b fix/#456-bug-description
```

### Commits

Format: `<emoji> <type>(<scope>): <subject>`

```bash
git commit -m "✨ feat(auth): add JWT refresh endpoint"
git commit -m "🐛 fix(orders): resolve race condition"
git commit -m "♻️ refactor(products): clean service architecture"
```

### Pull Requests

1. Tests passent: `make test-docker`
2. Code formaté: `./vendor/bin/pint`
3. Documentation à jour
4. Review requis

## Troubleshooting Courant

### Container ne démarre pas

```bash
docker-compose build --no-cache mon-service
docker-compose up -d mon-service
```

### Composer issues

```bash
docker-compose exec mon-service composer install
docker-compose exec mon-service composer update
```

### Cache problems

```bash
make clear-cache
docker-compose exec mon-service php artisan cache:clear
```

## Documentation

- **Quick Start:** [QUICK_START.md](QUICK_START.md)
- **Contributing:** [CONTRIBUTING.md](CONTRIBUTING.md)
- **Issues:** [ISSUES.md](ISSUES.md)
- **Architecture:** [../architecture/](../architecture/)
