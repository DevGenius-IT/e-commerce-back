# Global Database Architecture

## Table of Contents
- [Overview](#overview)
- [Database-per-Service Pattern](#database-per-service-pattern)
- [Database Inventory](#database-inventory)
- [Global Statistics](#global-statistics)
- [Table Categories](#table-categories)
- [Infrastructure Components](#infrastructure-components)
- [Global Entity Relationship Diagram](#global-entity-relationship-diagram)

## Overview

This e-commerce microservices platform implements a strict database-per-service pattern with 11 isolated MySQL 8.0 databases. Services communicate asynchronously via RabbitMQ message broker, ensuring complete decoupling and independent scalability.

**Architecture Principles:**
- Each service owns its database exclusively
- No direct database-to-database connections
- All cross-service data access via RabbitMQ events
- Eventual consistency model
- Independent schema evolution per service

## Database-per-Service Pattern

### Isolation Strategy

Each microservice maintains complete control over its data:

```
auth-service        ---> auth_service_db
addresses-service   ---> addresses_service
products-service    ---> products_service_db
baskets-service     ---> baskets_service_db
orders-service      ---> orders_service_db
deliveries-service  ---> deliveries_service_db
newsletters-service ---> newsletters_service_db
sav-service         ---> sav_service_db
contacts-service    ---> contacts_service_db
websites-service    ---> websites_service_db
questions-service   ---> questions_service_db
messages-broker     ---> messages_broker
```

### Cross-Service References

Services store foreign key IDs from other services but do NOT create database foreign key constraints. Data integrity is maintained through:

1. **Event-Driven Validation**: Services publish events when entities are created/modified
2. **Saga Pattern**: Complex workflows coordinate via message sequences
3. **Eventual Consistency**: Services synchronize data through event subscriptions
4. **Idempotent Operations**: All message handlers support replay

**Example Cross-Service References:**
```sql
-- orders-service stores user_id but doesn't FK to auth-service
CREATE TABLE orders (
    user_id BIGINT UNSIGNED,  -- Reference to auth-service.users
    billing_address_id BIGINT UNSIGNED,  -- Reference to addresses-service.addresses
    -- NO foreign key constraints to other services
);

-- baskets-service stores user_id
CREATE TABLE baskets (
    user_id BIGINT UNSIGNED,  -- Reference to auth-service.users
    -- NO foreign key constraints to other services
);
```

## Database Inventory

### 1. auth_service_db (Authentication & Authorization)
**Purpose:** User authentication, role-based access control (RBAC)
**External Port:** 3331
**Tables:** 9 (business: 1, system: 3, RBAC: 5)

| Table | Type | Description |
|-------|------|-------------|
| users | Business | User accounts, credentials, profiles |
| permissions | RBAC | Available system permissions |
| roles | RBAC | User role definitions |
| model_has_permissions | RBAC | User-permission assignments |
| model_has_roles | RBAC | User-role assignments |
| role_has_permissions | RBAC | Role-permission mappings |
| cache | System | Laravel cache storage |
| cache_locks | System | Cache locking mechanism |
| jobs | System | Queue job tracking |

**Key Features:**
- JWT token authentication
- Spatie Laravel Permission RBAC
- Team-based permissions support
- Guard-based multi-auth

---

### 2. addresses_service
**Purpose:** Address management and geographic validation
**External Port:** 3333
**Tables:** 3 (all business)

| Table | Type | Description |
|-------|------|-------------|
| countries | Business | Country definitions (ISO codes) |
| regions | Business | States/provinces within countries |
| addresses | Business | User addresses (billing/shipping) |

**Key Features:**
- Geographic hierarchy (country > region > address)
- Address type classification (billing/shipping/both)
- Default address marking
- Geocoding support (latitude/longitude)
- Cross-service: References `user_id` from auth-service

---

### 3. products_service_db
**Purpose:** Product catalog, inventory, and classification
**External Port:** 3307
**Tables:** 15 (business: 11, pivot: 4)

| Table | Type | Description |
|-------|------|-------------|
| vat | Business | VAT rate configurations |
| brands | Business | Product brands |
| types | Business | Product type taxonomy |
| categories | Business | Product category hierarchy |
| catalogs | Business | Product catalogs/collections |
| attribute_groups | Business | Attribute grouping |
| characteristic_groups | Business | Characteristic grouping |
| products | Business | Core product entities |
| attributes | Business | Product attributes (size, color) |
| related_characteristics | Business | Related characteristic links |
| characteristics | Business | Product characteristics |
| product_types | Pivot | Product-type associations |
| product_categories | Pivot | Product-category associations |
| product_catalogs | Pivot | Product-catalog associations |
| product_images | Business | Product image metadata |

**Key Features:**
- Complex product taxonomy (types, categories, catalogs)
- Attribute and characteristic management
- Multi-image support with MinIO storage
- Brand associations
- Stock tracking
- VAT calculation support

---

### 4. baskets_service_db
**Purpose:** Shopping cart management and promo codes
**External Port:** 3319
**Tables:** 5 (business: 4, pivot: 1)

| Table | Type | Description |
|-------|------|-------------|
| types | Business | Basket types |
| promo_codes | Business | Promotional codes |
| baskets | Business | User shopping carts |
| basket_items | Business | Cart line items |
| basket_promo_code | Pivot | Applied promo codes |

**Key Features:**
- User-specific baskets
- Line item management
- Promo code application
- Amount calculation
- Soft deletes for abandonment tracking
- Cross-service: References `user_id` and `product_id`

---

### 5. orders_service_db
**Purpose:** Order processing and order lifecycle management
**External Port:** 3330
**Tables:** 3 (all business)

| Table | Type | Description |
|-------|------|-------------|
| order_status | Business | Order status definitions |
| orders | Business | Customer orders |
| order_items | Business | Order line items |

**Key Features:**
- Unique order number generation
- Multi-amount tracking (HT, TTC, discount, VAT)
- Status-driven workflow
- Order notes support
- Comprehensive indexing for reporting
- Cross-service: References `user_id`, `billing_address_id`, `shipping_address_id`, `product_id`

---

### 6. deliveries_service_db
**Purpose:** Delivery tracking and logistics management
**External Port:** 3320
**Tables:** 3 (all business)

| Table | Type | Description |
|-------|------|-------------|
| status | Business | Delivery status definitions |
| sale_points | Business | Pickup/delivery locations |
| deliveries | Business | Delivery tracking records |

**Key Features:**
- Tracking number management
- Multiple delivery methods (standard, express, pickup)
- Carrier integration support
- Estimated vs actual delivery dates
- Special instructions and notes
- Sale point pickup support
- Cross-service: References `order_id`

---

### 7. newsletters_service_db
**Purpose:** Email marketing campaigns and subscriptions
**External Port:** 3321
**Tables:** 7 (business: 4, system: 3, pivot: 1)

| Table | Type | Description |
|-------|------|-------------|
| newsletters | Business | Newsletter subscriptions |
| campaigns | Business | Email campaigns |
| newsletter_campaigns | Pivot | Newsletter-campaign associations |
| email_templates | Business | Email template storage |
| users | System | Service-local user cache |
| cache | System | Laravel cache storage |
| jobs | System | Queue job tracking |

**Key Features:**
- Newsletter subscription management
- Campaign creation and tracking
- Template-based emails (MinIO storage)
- Multi-newsletter campaign support

---

### 8. sav_service_db (Customer Service)
**Purpose:** Support ticket management and customer service
**External Port:** 3322
**Tables:** 6 (business: 3, system: 3)

| Table | Type | Description |
|-------|------|-------------|
| support_tickets | Business | Customer support tickets |
| ticket_messages | Business | Ticket conversation threads |
| ticket_attachments | Business | File attachments (MinIO) |
| users | System | Service-local user cache |
| cache | System | Laravel cache storage |
| jobs | System | Queue job tracking |

**Key Features:**
- Ticket number generation
- Priority and status tracking
- Agent assignment
- Order association
- Multi-message conversations
- File attachment support (MinIO)
- Cross-service: References `user_id`, `order_id`

---

### 9. contacts_service_db
**Purpose:** Contact list management and CRM functionality
**External Port:** 3323
**Tables:** 8 (business: 4, system: 3, RBAC: 5, pivot: 1)

| Table | Type | Description |
|-------|------|-------------|
| contact_lists | Business | Contact list definitions |
| contacts | Business | Contact records |
| contact_tags | Business | Contact tagging system |
| contact_list_contacts | Pivot | List-contact associations |
| permissions | RBAC | Permission definitions |
| roles | RBAC | Role definitions |
| model_has_permissions | RBAC | User-permission assignments |
| model_has_roles | RBAC | User-role assignments |
| role_has_permissions | RBAC | Role-permission mappings |
| users | System | Service-local user cache |
| cache | System | Laravel cache storage |
| jobs | System | Queue job tracking |

**Key Features:**
- Multi-list contact management
- Tag-based organization
- RBAC for contact access control
- Email, phone, company tracking

---

### 10. websites_service_db
**Purpose:** Multi-site configuration and management
**External Port:** 3325
**Tables:** 4 (business: 1, system: 3)

| Table | Type | Description |
|-------|------|-------------|
| websites | Business | Website configurations |
| users | System | Service-local user cache |
| cache | System | Laravel cache storage |
| jobs | System | Queue job tracking |

**Key Features:**
- Multi-tenant website management
- Domain and configuration storage
- Website metadata

---

### 11. questions_service_db
**Purpose:** FAQ and Q&A system
**External Port:** 3324
**Tables:** 5 (business: 2, system: 3)

| Table | Type | Description |
|-------|------|-------------|
| questions | Business | FAQ questions |
| answers | Business | FAQ answers |
| users | System | Service-local user cache |
| cache | System | Laravel cache storage |
| jobs | System | Queue job tracking |

**Key Features:**
- Question-answer pairing
- Multi-answer support per question

---

### 12. messages_broker
**Purpose:** RabbitMQ message tracking and audit
**External Port:** 3332
**Tables:** 2 (all business)

| Table | Type | Description |
|-------|------|-------------|
| messages | Business | Message lifecycle tracking |
| failed_messages | Business | Dead letter queue storage |

**Key Features:**
- Message status tracking (pending, published, consumed, failed)
- Retry count management
- Error message storage
- Queue and routing key tracking
- Consumer audit trail

## Global Statistics

### Table Counts by Service
```
auth-service        : 9 tables (1 business, 3 system, 5 RBAC)
addresses-service   : 3 tables (3 business)
products-service    : 15 tables (11 business, 4 pivot)
baskets-service     : 5 tables (4 business, 1 pivot)
orders-service      : 3 tables (3 business)
deliveries-service  : 3 tables (3 business)
newsletters-service : 7 tables (4 business, 3 system, 1 pivot)
sav-service         : 6 tables (3 business, 3 system)
contacts-service    : 8 tables (4 business, 3 system, 5 RBAC, 1 pivot)
websites-service    : 4 tables (1 business, 3 system)
questions-service   : 5 tables (2 business, 3 system)
messages-broker     : 2 tables (2 business)
---
TOTAL              : 70 tables
```

### Table Distribution
- **Business Tables:** 39 (core business entities)
- **System Tables:** 21 (Laravel infrastructure: users, cache, jobs)
- **RBAC Tables:** 10 (5 per service using Spatie permissions)
- **Pivot Tables:** 7 (many-to-many relationships)

**Total Unique Tables:** 70 across 12 databases

### Cross-Service References
Services storing foreign IDs from other services:
- **baskets-service:** user_id, product_id
- **orders-service:** user_id, billing_address_id, shipping_address_id, product_id
- **deliveries-service:** order_id
- **sav-service:** user_id, order_id
- **addresses-service:** user_id

## Table Categories

### Business Tables (39)
Core domain entities representing business logic:
- User accounts and authentication
- Product catalog and inventory
- Shopping carts and orders
- Deliveries and logistics
- Customer service and support
- Contact management and newsletters

### System Tables (21)
Laravel framework infrastructure (repeated per service):
- **users:** Local user cache (9 instances)
- **cache:** Cache storage (7 instances)
- **cache_locks:** Cache locking (1 instance in auth-service)
- **jobs:** Queue job tracking (7 instances)

### RBAC Tables (10)
Spatie Laravel Permission tables (in auth-service and contacts-service):
- **permissions:** System permission definitions
- **roles:** User role definitions
- **model_has_permissions:** User-permission assignments (polymorphic)
- **model_has_roles:** User-role assignments (polymorphic)
- **role_has_permissions:** Role-permission mappings

### Pivot Tables (7)
Many-to-many relationship tables:
- product_types, product_categories, product_catalogs (products-service)
- basket_promo_code (baskets-service)
- newsletter_campaigns (newsletters-service)
- contact_list_contacts (contacts-service)
- role_has_permissions (auth-service, contacts-service)

## Infrastructure Components

### MySQL 8.0 Configuration
- **Version:** MySQL 8.0
- **Isolation:** One database per service
- **External Access:** Each database exposed on unique port for debugging
- **Credentials:** root/root (development only)
- **Charset:** utf8mb4 with utf8mb4_unicode_ci collation

### RabbitMQ Message Broker
- **Host:** rabbitmq:5672
- **Management UI:** http://localhost:15672
- **Exchange:** microservices_exchange (topic type)
- **Credentials:** guest/guest
- **Features:** Durable exchanges, persistent queues, message TTL

### MinIO Object Storage
- **Endpoint:** minio:9000
- **Console:** http://localhost:9001
- **Credentials:** admin/adminpass123
- **Buckets:** products, sav, newsletters
- **Integration:** AWS S3 SDK compatibility

### Redis Cache
- **Host:** redis:6379
- **Usage:** Distributed caching, session storage
- **Driver:** Laravel cache driver

## Global Entity Relationship Diagram

```mermaid
erDiagram
    %% Auth Service - Central Identity Provider
    AUTH_USERS ||--o{ ORDERS : "places"
    AUTH_USERS ||--o{ BASKETS : "owns"
    AUTH_USERS ||--o{ ADDRESSES : "has"
    AUTH_USERS ||--o{ SUPPORT_TICKETS : "creates"
    AUTH_USERS ||--o{ CONTACTS : "manages"
    AUTH_USERS ||--o| ROLES : "assigned"
    ROLES ||--o{ PERMISSIONS : "grants"

    %% Products Service - Catalog & Inventory
    PRODUCTS ||--o{ PRODUCT_TYPES : "categorized_by"
    PRODUCTS ||--o{ PRODUCT_CATEGORIES : "belongs_to"
    PRODUCTS ||--o{ PRODUCT_CATALOGS : "featured_in"
    PRODUCTS ||--|| BRANDS : "manufactured_by"
    PRODUCTS ||--o{ PRODUCT_IMAGES : "displays"
    PRODUCTS ||--o{ BASKET_ITEMS : "added_to"
    PRODUCTS ||--o{ ORDER_ITEMS : "ordered_as"
    PRODUCTS ||--|| VAT : "taxed_at"
    TYPES ||--o{ PRODUCT_TYPES : "defines"
    CATEGORIES ||--o{ PRODUCT_CATEGORIES : "organizes"
    CATALOGS ||--o{ PRODUCT_CATALOGS : "contains"
    ATTRIBUTES ||--|| ATTRIBUTE_GROUPS : "grouped_by"
    CHARACTERISTICS ||--|| CHARACTERISTIC_GROUPS : "grouped_by"
    CHARACTERISTICS ||--o{ RELATED_CHARACTERISTICS : "relates_to"

    %% Baskets Service - Shopping Cart
    BASKETS ||--o{ BASKET_ITEMS : "contains"
    BASKETS ||--o{ BASKET_PROMO_CODE : "applies"
    PROMO_CODES ||--o{ BASKET_PROMO_CODE : "used_in"
    BASKET_TYPES ||--o| BASKETS : "classifies"

    %% Orders Service - Order Processing
    ORDERS ||--o{ ORDER_ITEMS : "includes"
    ORDERS ||--|| ORDER_STATUS : "has_status"
    ORDERS ||--|| ADDRESSES : "ships_to"
    ORDERS ||--|| ADDRESSES : "bills_to"
    ORDERS ||--o| DELIVERIES : "fulfilled_by"

    %% Deliveries Service - Logistics
    DELIVERIES ||--|| DELIVERY_STATUS : "tracked_by"
    DELIVERIES ||--|| SALE_POINTS : "picked_up_at"

    %% Newsletters Service - Email Marketing
    NEWSLETTERS ||--o{ NEWSLETTER_CAMPAIGNS : "sent_via"
    CAMPAIGNS ||--o{ NEWSLETTER_CAMPAIGNS : "distributes"
    CAMPAIGNS ||--|| EMAIL_TEMPLATES : "uses"

    %% SAV Service - Customer Support
    SUPPORT_TICKETS ||--o{ TICKET_MESSAGES : "discussed_in"
    SUPPORT_TICKETS ||--o{ TICKET_ATTACHMENTS : "documented_by"
    SUPPORT_TICKETS ||--o| ORDERS : "related_to"

    %% Contacts Service - CRM
    CONTACTS ||--o{ CONTACT_LIST_CONTACTS : "member_of"
    CONTACT_LISTS ||--o{ CONTACT_LIST_CONTACTS : "contains"
    CONTACTS ||--o{ CONTACT_TAGS : "tagged_with"

    %% Questions Service - FAQ
    QUESTIONS ||--o{ ANSWERS : "answered_by"

    %% Websites Service - Multi-site Config
    WEBSITES ||--o{ PRODUCTS : "sells"
    WEBSITES ||--o{ ORDERS : "processes"

    %% Messages Broker - Event Tracking
    MESSAGES ||--o| FAILED_MESSAGES : "becomes_on_error"

    %% Cross-Service Event Flow (Dashed = Async via RabbitMQ)
    BASKETS -.-> ORDERS : "checkout_event / converts_to"
    ORDERS -.-> DELIVERIES : "order_created / triggers"
    ORDERS -.-> PRODUCTS : "order_created / updates_inventory"
    AUTH_USERS -.-> MESSAGES : "user_events / tracked_by"
    PRODUCTS -.-> MESSAGES : "product_events / tracked_by"
    ORDERS -.-> MESSAGES : "order_events / tracked_by"
```

### Diagram Legend
- **Solid Lines (||--):** Intra-service foreign key relationships
- **Dashed Lines (-.->):** Cross-service async communication via RabbitMQ
- **Cardinality:**
  - `||--o{` : One-to-many
  - `||--||` : One-to-one
  - `||--o|` : One-to-zero-or-one

### Key Architectural Patterns

1. **Central Identity:** Auth service provides user authentication for all services
2. **Product Hub:** Products service is referenced by baskets and orders
3. **Order Lifecycle:** Basket -> Order -> Delivery workflow
4. **Event Sourcing:** All cross-service communication via RabbitMQ events
5. **Data Duplication:** Services cache foreign entity data locally (eventual consistency)

## Database Connection Configuration

### Service-Specific Connection Strings

Each service connects to its dedicated database:

```env
# Auth Service
DB_HOST=auth-db
DB_PORT=3306
DB_DATABASE=auth_service_db
DB_USERNAME=root
DB_PASSWORD=root

# Products Service
DB_HOST=products-db
DB_PORT=3306
DB_DATABASE=products_service_db
DB_USERNAME=root
DB_PASSWORD=root

# [Similar configuration for all 12 services]
```

### External Database Ports (Development Access)

For debugging and administration, databases are exposed on host machine:

```
auth-db              : localhost:3331
messages-broker-db   : localhost:3332
addresses-db         : localhost:3333
products-db          : localhost:3307
baskets-db           : localhost:3319
orders-db            : localhost:3330
deliveries-db        : localhost:3320
newsletters-db       : localhost:3321
sav-db               : localhost:3322
contacts-db          : localhost:3323
questions-db         : localhost:3324
websites-db          : localhost:3325
```

**Connection Example:**
```bash
mysql -h 127.0.0.1 -P 3331 -u root -p auth_service_db
# Password: root
```

## Migration Management

### Running Migrations

```bash
# Migrate all services
make migrate-all

# Migrate specific service
docker-compose exec auth-service php artisan migrate

# Fresh migration with seeds (destructive)
make fresh-all

# Rollback last migration batch
docker-compose exec auth-service php artisan migrate:rollback
```

### Migration Organization

Migrations are prefixed with timestamps for ordering:
- `0001_01_01_*` : Laravel framework tables (users, cache, jobs)
- `2024_01_01_*` : Business domain tables
- `2025_09_*` : Recent feature additions

## Data Integrity Strategy

### Within-Service Integrity
- Standard foreign key constraints
- Cascade delete for dependent entities
- Check constraints for data validation
- Unique constraints for business keys

### Cross-Service Integrity
- **No foreign key constraints** between services
- Event-driven validation via RabbitMQ
- Saga pattern for distributed transactions
- Idempotent message handlers
- Eventual consistency model
- Retry mechanisms with exponential backoff

### Referential Integrity Events

When a service modifies entities referenced by other services, it publishes events:

```
user.created      -> All services update local user cache
user.updated      -> Services invalidate cached user data
product.deleted   -> Baskets/Orders mark products as unavailable
order.cancelled   -> Deliveries cancel shipment
```

## Backup and Disaster Recovery

### Backup Strategy

```bash
# Backup all databases
make backup-docker

# Output: ./backups/YYYY-MM-DD_HH-MM-SS/
#   auth_service_db.sql
#   products_service_db.sql
#   [all 12 databases]
```

### Recovery Procedure

```bash
# Restore specific database
docker-compose exec auth-db mysql -u root -p auth_service_db < backup.sql

# Full system restore
for db in backups/*.sql; do
  service=$(basename $db .sql)
  docker-compose exec ${service}-db mysql -u root -p ${service} < $db
done
```

## Performance Optimization

### Indexing Strategy
- **Primary Keys:** All tables use auto-incrementing bigint primary keys
- **Foreign Keys:** Indexed for join performance
- **Search Fields:** Name, reference, status columns indexed
- **Composite Indexes:** Multi-column indexes for common queries
- **Temporal Indexes:** created_at, updated_at for time-series queries

### Query Optimization
- **Eager Loading:** Services use Eloquent eager loading to prevent N+1 queries
- **Query Caching:** Read-heavy tables cached in Redis
- **Pagination:** All list endpoints paginated
- **Database Connection Pooling:** Persistent connections in production

### Scaling Strategies
- **Read Replicas:** Services can add read replicas independently
- **Sharding:** Possible at service level (e.g., products by region)
- **Caching Layer:** Redis reduces database load
- **Async Processing:** Heavy operations offloaded to queues

## Security Considerations

### Database Security
- **Network Isolation:** Databases only accessible within Docker network
- **Credential Management:** Environment variable configuration
- **Production Hardening:** Root access disabled, dedicated service users
- **SSL/TLS:** Encrypted connections in production
- **Audit Logging:** Query logs for sensitive operations

### Access Control
- **Service Isolation:** Services cannot access other databases
- **RBAC:** Fine-grained permissions in auth and contacts services
- **SQL Injection Prevention:** Eloquent ORM with parameter binding
- **Prepared Statements:** All queries use prepared statements

## Monitoring and Observability

### Health Checks
```bash
# Check all database connections
make health-docker

# Individual service health
docker-compose exec auth-service php artisan db:monitor
```

### Metrics to Track
- Connection pool utilization
- Query execution time
- Slow query log analysis
- Database size and growth rate
- Index usage statistics
- Replication lag (if using replicas)

## Future Considerations

### Schema Evolution
- **Backward Compatibility:** New columns with defaults
- **Versioned APIs:** Services maintain API versioning
- **Migration Strategy:** Blue-green deployments for schema changes
- **Event Schema Versioning:** RabbitMQ message versioning

### Scalability Planning
- **Horizontal Scaling:** Add service replicas with load balancing
- **Database Sharding:** Partition large tables (products, orders)
- **CQRS Pattern:** Separate read/write models for high-traffic services
- **Event Sourcing:** Consider event store for complete audit trail

### Technology Migration
- **Database Alternatives:** Evaluate PostgreSQL for JSON/JSONB support
- **NoSQL Integration:** Consider MongoDB for document-heavy services (products)
- **NewSQL Options:** Evaluate CockroachDB for global distribution
- **Cloud Native:** Migration path to managed database services (RDS, Aurora)
