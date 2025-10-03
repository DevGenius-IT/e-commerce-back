# Addresses Service

## Service Overview

**Purpose**: Address management and validation for shipping and billing addresses.

**Port**: 8004
**Database**: addresses_db (MySQL 8.0)
**External Port**: 3311 (for debugging)
**Dependencies**: Auth service (user authentication)

## Responsibilities

- Address CRUD operations
- Shipping and billing address management
- Default address selection
- Address validation
- Country and region reference data
- Address type classification (shipping, billing, both)

## API Endpoints

### Public Endpoints

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Service health check | No | {"status":"healthy","service":"addresses-service"} |
| GET | /countries | List countries | No | [{"id","name","code","regions":[]}] |
| GET | /countries/{id} | Get country details | No | Country object |
| GET | /countries/{id}/regions | Get country regions | No | [{"id","name","code"}] |

### User Endpoints (Auth Required via RabbitMQ)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /addresses | List user's addresses | [{"id","type","street","city","country"}] |
| POST | /addresses | Create address | Address data -> Created address |
| GET | /addresses/type/{type} | Filter by type | Addresses filtered by type (shipping, billing) |
| GET | /addresses/{id} | Get address details | Full address object |
| PUT | /addresses/{id} | Update address | Address data -> Updated address |
| PATCH | /addresses/{id} | Partial update | Partial data -> Updated address |
| DELETE | /addresses/{id} | Delete address | Success message |
| POST | /addresses/{id}/set-default | Set as default | {"type"} -> Success |

## Database Schema

**Tables**:

1. **addresses** - User addresses
   - id (PK)
   - user_id (FK)
   - type (enum: shipping, billing, both)
   - label (string - e.g., "Home", "Office")
   - first_name
   - last_name
   - company (nullable)
   - street_1
   - street_2 (nullable)
   - city
   - region_id (FK, nullable)
   - postal_code
   - country_id (FK)
   - phone
   - email (nullable)
   - is_default_shipping (boolean)
   - is_default_billing (boolean)
   - delivery_instructions (text, nullable)
   - timestamps

2. **countries** - Country reference data
   - id (PK)
   - name
   - code (ISO 3166-1 alpha-2, unique)
   - code_3 (ISO 3166-1 alpha-3)
   - numeric_code (ISO 3166-1 numeric)
   - phone_code
   - currency_code
   - is_active (boolean)
   - timestamps

3. **regions** - State/province/region data
   - id (PK)
   - country_id (FK)
   - name
   - code (unique within country)
   - type (state, province, region, etc.)
   - is_active (boolean)
   - timestamps

**Relationships**:
- Address -> User (belongs to)
- Address -> Country (belongs to)
- Address -> Region (belongs to, nullable)
- Country -> Regions (has many)

## Address Validation

**Validation Rules**:
- Required fields: first_name, last_name, street_1, city, country
- Postal code format validation by country
- Phone number format validation
- Region required for certain countries (US, CA, etc.)
- Street length limits
- Character encoding validation (UTF-8)

**Validation Service Integration** (Future):
- Google Maps API for address verification
- USPS address validation for US addresses
- Real-time address autocomplete

## RabbitMQ Integration

**Events Consumed**:
- `user.created` - Initialize default address template
- `order.created` - Log address usage for analytics

**Events Published**:
- `address.created` - New address added
- `address.updated` - Address modified
- `address.deleted` - Address removed
- `address.default.changed` - Default address updated

**Message Format Example**:
```json
{
  "event": "address.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "address_id": 123,
    "user_id": 456,
    "type": "shipping",
    "country": "US",
    "is_default": true
  }
}
```

## Environment Variables

```bash
# Application
APP_NAME=addresses-service
APP_ENV=local
APP_PORT=8004

# Database
DB_CONNECTION=mysql
DB_HOST=addresses-mysql
DB_PORT=3306
DB_DATABASE=addresses_db
DB_USERNAME=addresses_user
DB_PASSWORD=addresses_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=addresses_exchange
RABBITMQ_QUEUE=addresses_queue

# Service URLs
AUTH_SERVICE_URL=http://auth-service:8000
```

## Deployment

**Docker Configuration**:
```yaml
Service: addresses-service
Port Mapping: 8004:8000
Database: addresses-mysql (port 3311 external)
Depends On: addresses-mysql, rabbitmq
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 2 replicas
- CPU Request: 100m, Limit: 200m
- Memory Request: 256Mi, Limit: 512Mi
- Service Type: ClusterIP

**Health Check Configuration**:
- Liveness Probe: GET /health (10s interval)
- Readiness Probe: Database connectivity
- Startup Probe: 30s timeout

## Performance Optimization

**Caching Strategy**:
- Countries and regions cached indefinitely (rarely change)
- User addresses cached (5 min TTL)
- Default address lookups cached per user

**Database Optimization**:
- Indexes on: user_id, country_id, region_id
- Composite indexes for default address lookups
- Full-text search on street, city for address search

## Monitoring and Observability

**Metrics to Track**:
- Address creation rate
- Addresses per user (average)
- Default address change frequency
- Validation failures

**Logging**:
- Address CRUD operations
- Default address changes
- Validation errors

## Future Enhancements

- Address verification with external APIs
- Geolocation and coordinates storage
- Address autocomplete integration
- International address format templates
- PO Box detection and handling
- Business vs residential classification
- Address quality scoring
