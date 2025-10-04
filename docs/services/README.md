# E-Commerce Microservices Documentation

Comprehensive documentation for all 13 microservices in the e-commerce platform.

## Architecture Overview

This platform implements a fully asynchronous microservices architecture with:
- **Communication**: RabbitMQ message broker for all inter-service communication
- **Storage**: MinIO distributed object storage for files and images
- **Authentication**: Centralized JWT-based authentication via auth-service
- **Gateway**: Single API Gateway as entry point for all client requests

## Service Documentation

### Infrastructure Services

1. **[API Gateway](01-api-gateway.md)** - Port 8100
   - Single entry point for all requests
   - Routes to microservices via RabbitMQ
   - Service discovery and health monitoring

2. **[Messages Broker](13-messages-broker.md)** - Port 8011
   - RabbitMQ coordination and management
   - Failed message tracking and retry logic
   - Queue monitoring and statistics

### Core Services

3. **[Auth Service](02-auth-service.md)** - Port 8000
   - JWT authentication and token management
   - Role-based access control (RBAC) with Spatie Laravel Permission
   - User management and authorization

4. **[Products Service](03-products-service.md)** - Port 8001
   - Product catalog management
   - Categories, brands, types, and catalogs
   - Inventory tracking
   - MinIO integration for product images

5. **[Baskets Service](04-baskets-service.md)** - Port 8002
   - Shopping cart management
   - Promotional code handling
   - Price calculations and totals

6. **[Orders Service](05-orders-service.md)** - Port 8003
   - Order processing and lifecycle management
   - State machine for order status transitions
   - Payment status tracking

7. **[Addresses Service](06-addresses-service.md)** - Port 8004
   - Address management and validation
   - Shipping and billing addresses
   - Country and region reference data

8. **[Deliveries Service](07-deliveries-service.md)** - Port 8005
   - Delivery tracking and shipment management
   - Sale point (pickup location) management
   - Carrier integration coordination

### Marketing and Support Services

9. **[Newsletters Service](08-newsletters-service.md)** - Port 8006
   - Email newsletter subscription management
   - Campaign creation and execution
   - MinIO integration for email templates
   - Email delivery tracking and analytics

10. **[SAV Service](09-sav-service.md)** - Port 8007 (Customer Support)
    - Support ticket management system
    - Message threading and conversations
    - MinIO integration for ticket attachments
    - Ticket lifecycle and assignment

11. **[Contacts Service](10-contacts-service.md)** - Port 8008
    - Contact database management (CRM)
    - List segmentation and tagging
    - Email engagement tracking
    - Marketing automation integration

12. **[Questions Service](11-questions-service.md)** - Port 8009
    - FAQ management system
    - Question and answer management
    - Public FAQ access with search
    - Helpfulness tracking

### Configuration Services

13. **[Websites Service](12-websites-service.md)** - Port 8010
    - Multi-site configuration and tenant management
    - Website-specific settings and branding
    - Domain and locale management

## Service Ports Reference

| Service | Internal Port | External DB Port | Database Name |
|---------|---------------|------------------|---------------|
| API Gateway | 8100 | N/A | None (stateless) |
| Auth Service | 8000 | 3307 | auth_db |
| Products Service | 8001 | 3308 | products_db |
| Baskets Service | 8002 | 3309 | baskets_db |
| Orders Service | 8003 | 3310 | orders_db |
| Addresses Service | 8004 | 3311 | addresses_db |
| Deliveries Service | 8005 | 3312 | deliveries_db |
| Newsletters Service | 8006 | 3313 | newsletters_db |
| SAV Service | 8007 | 3314 | sav_db |
| Contacts Service | 8008 | 3315 | contacts_db |
| Questions Service | 8009 | 3316 | questions_db |
| Websites Service | 8010 | 3317 | websites_db |
| Messages Broker | 8011 | 3318 | messages_broker_db |

## Infrastructure Components

### RabbitMQ
- **Ports**: 5672 (AMQP), 15672 (Management UI)
- **Management UI**: http://localhost:15672
- **Credentials**: guest/guest
- **Purpose**: Asynchronous message broker for all inter-service communication

### MinIO Object Storage
- **Ports**: 9000 (API), 9001 (Console)
- **Console**: http://localhost:9001
- **Credentials**: admin/adminpass123
- **Buckets**:
  - `products` - Product images
  - `sav` - Support ticket attachments
  - `newsletters` - Email templates

### Nginx Reverse Proxy
- **Ports**: 80 (HTTP), 443 (HTTPS)
- **Routes**: All `/api/` and `/v1/` traffic to API Gateway
- **Headers**: X-Request-ID for request tracing

### MySQL Databases
- **Version**: 8.0
- **Pattern**: Database-per-service isolation
- **External Ports**: 3307-3318 (for debugging)

## Communication Patterns

### Request-Response Flow
```
Client -> Nginx -> API Gateway -> RabbitMQ -> Microservice
                                     ^              |
                                     |              v
                                  Response <--- Process
```

### Event Publishing
```
Service A -> Event -> RabbitMQ Exchange -> Multiple Queues
                                             |  |  |
                                             v  v  v
                                          Service B, C, D
```

### Common Events

**Order Flow**:
```
basket.checkout -> order.created -> inventory.reserve -> payment.process
-> order.confirmed -> delivery.create -> delivery.shipped -> order.delivered
```

**User Registration**:
```
user.registered -> contact.created -> newsletter.subscribe (optional)
```

**Product Updates**:
```
product.updated -> basket.price.sync -> order.price.validate
```

## Development Workflow

### Quick Start
```bash
make docker-install    # First time setup
make dev              # Daily development
make health-docker    # Check service health
```

### Working with Services
```bash
# Access service shell
docker-compose exec <service-name> bash

# Run migrations
docker-compose exec <service-name> php artisan migrate

# Run tests
docker-compose exec <service-name> php artisan test

# View logs
docker-compose logs -f <service-name>
```

### Database Management
```bash
make migrate-all      # Run migrations on all services
make seed-all         # Run seeders
make fresh-all        # Fresh migration with seeds
make backup-docker    # Backup all databases
```

## Testing

### Service-Level Testing
Each service has its own test suite:
```bash
# Run tests for specific service
make test-service SERVICE_NAME=auth-service

# Run all service tests
make test-docker
```

### API Testing
Postman collections available in `docs/api/postman/`:
- Complete E-commerce API v2 collection
- Environment files (Development, Staging, Production)
- Automated tests and validation

## Monitoring and Health Checks

### Health Endpoints
All services expose:
- `GET /health` - JSON health response
- `GET /simple-health` or `/status` - Text response for probes

### Monitoring Tools
```bash
make docker-status     # Service status overview
make stats            # Resource usage
make docker-endpoints # Service URLs
```

## Security Considerations

### Authentication
- JWT tokens (60-minute TTL)
- Refresh tokens (2-week TTL)
- Role-based permissions (RBAC)
- Shared JWT validation middleware

### Data Protection
- Database-per-service isolation
- Encrypted passwords (bcrypt)
- Secure file storage (MinIO with presigned URLs)
- HTTPS in production

### Access Control
- Public endpoints (no auth)
- User endpoints (JWT required)
- Admin endpoints (JWT + admin role)

## Performance Targets

- Response Time: < 500ms for API calls
- Throughput: 1000 req/s per service
- Availability: 99.9% uptime
- Auto-scaling enabled on Kubernetes

## Deployment

### Docker (Development)
```bash
docker-compose up --watch
```

### Kubernetes (Production)
```bash
make k8s-setup       # Initial setup
make k8s-deploy      # Deploy services
make k8s-status      # Check deployment
make k8s-monitoring  # Open dashboards
```

## Additional Documentation

- **Architecture**: `docs/architecture/`
- **API Documentation**: `docs/api/postman/`
- **Deployment Guides**: `docs/deployment/`
- **Development**: `docs/development/`
- **Maintenance**: `docs/maintenance/`

## Contributing

When adding new features or modifying services:
1. Update service documentation in this directory
2. Update API Postman collections
3. Run tests and ensure they pass
4. Update relevant architectural diagrams
5. Follow commit conventions (Gitmoji + Conventional Commits)

## Support

For questions or issues:
- Check individual service documentation
- Review RabbitMQ management UI for message flow issues
- Check service logs: `docker-compose logs -f <service>`
- Review troubleshooting sections in service docs
