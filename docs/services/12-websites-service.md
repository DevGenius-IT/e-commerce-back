# Websites Service

## Service Overview

**Purpose**: Multi-site configuration and tenant management for supporting multiple e-commerce websites from a single platform.

**Port**: 8010
**Database**: websites_db (MySQL 8.0)
**External Port**: 3317 (for debugging)
**Dependencies**: Auth service (for admin operations)

## Responsibilities

- Website/tenant configuration management
- Multi-site support (e.g., different brands, regions)
- Website-specific settings
- Domain management
- Theme and branding configuration
- Locale and currency settings
- Website analytics and metrics

## API Endpoints

### Health Check

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Service health check | No | {"status":"ok","service":"websites-service"} |

### Public Endpoints

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /websites | List active websites | No | [{"id","name","domain","locale"}] |
| GET | /websites/search | Search websites | No | Query: q -> Matching websites |
| GET | /websites/{id} | Get website details | No | Full website configuration |

### Protected Endpoints (Auth Required)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| POST | /websites | Create website | Website data -> Created website |
| PUT | /websites/{id} | Update website | Updated website |
| DELETE | /websites/{id} | Delete website | Success message |

## Database Schema

**Tables**:

1. **websites** - Website/tenant configurations
   - id (PK)
   - name
   - slug (unique)
   - domain (unique - e.g., shop.example.com)
   - description (text, nullable)
   - logo_url (nullable)
   - favicon_url (nullable)
   - status (enum: active, inactive, maintenance)
   - locale (default: en_US)
   - timezone (default: UTC)
   - currency (default: USD)
   - theme (string, nullable - theme identifier)
   - settings (JSON - website-specific configuration)
   - contact_email
   - support_email (nullable)
   - phone (nullable)
   - address (JSON, nullable)
   - social_media (JSON - links to social profiles)
   - analytics_id (string, nullable - Google Analytics, etc.)
   - is_default (boolean)
   - created_by (FK to users, nullable)
   - timestamps, soft_deletes

**Settings JSON Structure**:
```json
{
  "features": {
    "enable_wishlist": true,
    "enable_reviews": true,
    "enable_chat": false
  },
  "checkout": {
    "guest_checkout": true,
    "require_phone": true
  },
  "shipping": {
    "free_shipping_threshold": 50.00,
    "allow_pickup": true
  },
  "payment": {
    "methods": ["credit_card", "paypal", "bank_transfer"]
  },
  "branding": {
    "primary_color": "#007bff",
    "secondary_color": "#6c757d",
    "font_family": "Roboto"
  }
}
```

## Multi-Tenant Architecture

**Use Cases**:
- Multiple brands under one platform
- Regional websites (US, EU, Asia)
- White-label solutions for partners
- Testing and staging environments

**Tenant Isolation**:
- Data segregated by website_id
- Shared product catalog (optional)
- Independent user bases (optional)
- Separate analytics and reporting

## Website Configuration

**Core Settings**:
- Basic information (name, domain, description)
- Branding (logo, favicon, colors, fonts)
- Localization (language, timezone, currency)
- Contact information
- Feature toggles

**Feature Flags**:
- Enable/disable features per website
- A/B testing support
- Gradual rollout capability

## RabbitMQ Integration

**Events Consumed**:
- `user.activity` - Track activity per website
- `order.completed` - Website-specific analytics

**Events Published**:
- `website.created` - New website configured
- `website.updated` - Configuration changed
- `website.status.changed` - Status update (active, maintenance, etc.)

**Message Format Example**:
```json
{
  "event": "website.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "website_id": 123,
    "name": "My Online Store",
    "domain": "shop.example.com",
    "locale": "en_US",
    "currency": "USD"
  }
}
```

## Environment Variables

```bash
# Application
APP_NAME=websites-service
APP_ENV=local
APP_PORT=8010

# Database
DB_CONNECTION=mysql
DB_HOST=websites-mysql
DB_PORT=3306
DB_DATABASE=websites_db
DB_USERNAME=websites_user
DB_PASSWORD=websites_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=websites_exchange
RABBITMQ_QUEUE=websites_queue

# Service URLs
AUTH_SERVICE_URL=http://auth-service:8000
```

## Deployment

**Docker Configuration**:
```yaml
Service: websites-service
Port Mapping: 8010:8000
Database: websites-mysql (port 3317 external)
Depends On: websites-mysql, rabbitmq
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 2 replicas
- CPU Request: 50m, Limit: 150m
- Memory Request: 128Mi, Limit: 256Mi
- Service Type: ClusterIP

**Health Check Configuration**:
- Liveness Probe: GET /health (10s interval)
- Readiness Probe: Database connectivity
- Startup Probe: 30s timeout

## Performance Optimization

**Caching Strategy**:
- Website configurations cached (30 min TTL)
- Active websites list cached (15 min TTL)
- Website lookup by domain cached (30 min TTL)

**Database Optimization**:
- Indexes on: slug, domain, status
- Cache frequently accessed configurations

## Monitoring and Observability

**Metrics to Track**:
- Total active websites
- Websites by status
- Configuration changes
- Domain resolution issues

**Logging**:
- Website CRUD operations
- Configuration changes
- Status transitions

## Future Enhancements

- Multi-domain support per website
- Theme marketplace
- Website templates
- Automated website provisioning
- Website performance monitoring
- SEO configuration management
- Custom domain SSL management
