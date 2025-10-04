# Architecture E-commerce Platform

## Vue d'ensemble

Plateforme e-commerce microservices avec communication asynchrone via RabbitMQ et stockage distribué MinIO.

### Communication Flow

```
Client → Nginx → API Gateway → RabbitMQ → Microservices
                                    ↓
                             MinIO Storage
```

## Services Actifs

| Service | Port | Fonction |
|---------|------|----------|
| **api-gateway** | 8100 | Point d'entrée, routing, orchestration |
| **auth-service** | 8001 | JWT authentication, RBAC |
| **products-service** | 8003 | Catalogue produits, inventory |
| **orders-service** | 8004 | Gestion commandes |
| **baskets-service** | 8005 | Paniers d'achat |
| **deliveries-service** | 8006 | Livraisons |
| **newsletters-service** | 8007 | Campagnes email |
| **sav-service** | 8008 | Support client |
| **addresses-service** | 8009 | Adresses |
| **contacts-service** | 8010 | Formulaires contact |
| **questions-service** | 8012 | FAQ |
| **websites-service** | 8012 | Configuration sites |

## Infrastructure

### Message Broker (RabbitMQ)
- Ports: 5672 (AMQP), 15672 (Management)
- Exchanges et queues dédiés par service
- Management UI: http://localhost:15672

### Object Storage (MinIO)
- Ports: 9000 (API), 9001 (Console)
- Buckets: products, sav, newsletters
- Console: http://localhost:9001

### Databases (MySQL 8.0)
- Database-per-service pattern
- Isolation complète par service
- Ports externes pour debugging

### Reverse Proxy (Nginx)
- Routes `/api/` et `/v1/` vers API Gateway
- Load balancing
- Custom headers (X-Request-ID)

## Technologies

**Backend:**
- PHP 8.3+ / Laravel 12
- JWT Authentication (tymon/jwt-auth)
- RBAC (Spatie Laravel Permission)
- RabbitMQ (php-amqplib)
- MinIO S3 SDK (aws/aws-sdk-php)

**Infrastructure:**
- Docker & Docker Compose
- Kubernetes (production)
- Nginx
- MySQL 8.0

**Testing:**
- PHPUnit
- Laravel Pint (PSR-12)
- Postman collections

## Patterns Architecturaux

### Microservices
- Service indépendant avec sa DB
- Communication asynchrone via RabbitMQ
- API REST par service

### Event-Driven
- Messages RabbitMQ pour inter-service
- Publish/Subscribe pattern
- Queues dédiées

### CQRS
- Séparation commandes/requêtes
- Optimisation lecture/écriture

### API Gateway
- Point d'entrée unique
- Routing intelligent
- Orchestration services

## Sécurité

- JWT tokens (auth centralisée)
- Role-based permissions
- Presigned URLs (MinIO)
- File sanitization
- Rate limiting (API Gateway)

## Performance

**Targets:**
- Response time: <500ms
- Throughput: 1000 req/s par service
- Availability: 99.9%

## Documentation

- **Plan Architecture:** [ARCHITECTURE_PLAN.md](ARCHITECTURE_PLAN.md)
- **Roadmap:** [PROJECT_ROADMAP.md](PROJECT_ROADMAP.md)
- **Deployment:** [../deployment/](../deployment/)
- **Development:** [../development/](../development/)
