# Baskets Service

## Service Overview

**Purpose**: Shopping cart management including basket items, promo codes, and cart calculations.

**Port**: 8002
**Database**: baskets_db (MySQL 8.0)
**External Port**: 3309 (for debugging)
**Dependencies**: Auth service (JWT validation), Products service (product details, pricing, stock)

## Responsibilities

- Shopping basket lifecycle management
- Basket item management (add, update, remove)
- Promotional code application and validation
- Price calculations (subtotal, discounts, totals)
- Basket persistence across sessions
- Basket type management (standard, saved, abandoned)
- Integration with products service for real-time pricing

## API Endpoints

### Public Endpoints

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Service health check | No | {"status":"healthy","service":"baskets-service"} |
| POST | /promo-codes/validate | Validate promo code | No | {"code"} -> {"valid":true,"discount":{}} |

### User Endpoints (Auth Required)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /baskets/current | Get user's current basket | Basket with items and totals |
| POST | /baskets/items | Add item to basket | {"product_id","quantity","options"} -> Updated basket |
| PUT | /baskets/items/{id} | Update basket item | {"quantity","options"} -> Updated basket |
| DELETE | /baskets/items/{id} | Remove basket item | Success message |
| POST | /baskets/promo-codes | Apply promo code | {"code"} -> Updated basket with discount |
| DELETE | /baskets/promo-codes/{id} | Remove promo code | Updated basket |
| DELETE | /baskets/clear | Clear entire basket | Success message |

### Admin Endpoints (Auth Required + Admin Role)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /admin/baskets | List all baskets | Paginated basket list with filters |
| GET | /admin/baskets/{id} | Get basket details | Full basket object |
| DELETE | /admin/baskets/{id} | Delete basket | Success message |
| GET | /admin/promo-codes | List promo codes | [{"code","type","value","usage"}] |
| POST | /admin/promo-codes | Create promo code | Promo code data -> Created code |
| GET | /admin/promo-codes/{id} | Get promo code details | Promo code with usage stats |
| PUT | /admin/promo-codes/{id} | Update promo code | Updated promo code |
| DELETE | /admin/promo-codes/{id} | Delete promo code | Success message |
| GET | /admin/types | List basket types | Type list |
| POST | /admin/types | Create basket type | Type data -> Created type |
| GET | /admin/types/{id} | Get type details | Type object |
| PUT | /admin/types/{id} | Update type | Updated type |
| DELETE | /admin/types/{id} | Delete type | Success message |

## Database Schema

**Tables**:

1. **baskets** - Shopping cart instances
   - id (PK)
   - user_id (FK, nullable for guest baskets)
   - session_id (for guest baskets)
   - type_id (FK - active, saved, abandoned)
   - subtotal (decimal)
   - discount_amount (decimal)
   - tax_amount (decimal)
   - total (decimal)
   - currency (default: USD)
   - ip_address
   - user_agent
   - converted_to_order_id (FK, nullable)
   - abandoned_at (timestamp, nullable)
   - timestamps

2. **basket_items** - Items in baskets
   - id (PK)
   - basket_id (FK)
   - product_id (FK - referenced from products service)
   - quantity (integer)
   - unit_price (decimal - snapshot at time of add)
   - total_price (decimal - calculated)
   - product_snapshot (JSON - product details at time of add)
   - options (JSON - size, color, etc.)
   - timestamps

3. **promo_codes** - Promotional discount codes
   - id (PK)
   - code (unique)
   - description
   - type (enum: percentage, fixed_amount, free_shipping)
   - value (decimal - percentage or amount)
   - minimum_purchase (decimal, nullable)
   - maximum_discount (decimal, nullable)
   - usage_limit (integer, nullable)
   - usage_count (integer, default 0)
   - per_user_limit (integer, nullable)
   - start_date (timestamp)
   - end_date (timestamp, nullable)
   - is_active (boolean)
   - applicable_products (JSON - product IDs, nullable)
   - applicable_categories (JSON - category IDs, nullable)
   - timestamps

4. **basket_promo_code** - Applied promo codes (many-to-many)
   - id (PK)
   - basket_id (FK)
   - promo_code_id (FK)
   - discount_amount (decimal - calculated discount)
   - applied_at (timestamp)

5. **types** - Basket types/states
   - id (PK)
   - name (active, saved, abandoned, completed)
   - description
   - timestamps

**Relationships**:
- Basket -> User (belongs to, nullable)
- Basket -> Type (belongs to)
- Basket -> Items (has many)
- Basket -> PromoCodes (many-to-many)
- BasketItem -> Product (referenced via product_id)

## Basket Calculations

**Price Calculation Flow**:
1. Fetch current product prices from Products service
2. Calculate item subtotals: quantity * unit_price
3. Sum all item subtotals = basket subtotal
4. Apply promo codes (in order of application)
5. Calculate tax based on shipping address (future)
6. Calculate final total: subtotal - discounts + tax

**Promo Code Application Rules**:
- Codes applied in order they were added
- Stackable codes (configurable per code)
- Minimum purchase requirements checked
- Maximum discount caps enforced
- Per-user usage limits tracked
- Date range validation
- Product/category applicability checked

## RabbitMQ Integration

**Events Consumed**:
- `product.price.changed` - Update basket item prices
- `product.deleted` - Remove product from baskets
- `product.out_of_stock` - Flag item as unavailable
- `order.created` - Mark basket as converted

**Events Published**:
- `basket.created` - New basket created (for analytics)
- `basket.item.added` - Item added to basket
- `basket.item.removed` - Item removed from basket
- `basket.abandoned` - Basket inactive for 24 hours (for remarketing)
- `basket.converted` - Basket converted to order
- `promo_code.applied` - Promo code used (for tracking)

**Message Format Example**:
```json
{
  "event": "basket.item.added",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "basket_id": 456,
    "user_id": 123,
    "product_id": 789,
    "quantity": 2,
    "unit_price": 29.99,
    "total_price": 59.98
  }
}
```

## Integration with Products Service

**Product Information Sync**:
- Fetch product details when adding to basket
- Store product snapshot in basket_items.product_snapshot
- Validate product availability before adding
- Check stock levels before checkout
- Update prices if changed since last sync

**Product Snapshot Format**:
```json
{
  "product_id": 789,
  "name": "Product Name",
  "sku": "PROD-001",
  "description": "Product description",
  "image_url": "https://minio/products/789/image.jpg",
  "original_price": 29.99,
  "current_price": 24.99,
  "snapshot_at": "2024-01-15T10:30:00Z"
}
```

## Basket Lifecycle

**States**:
1. **Active** - Current shopping session
2. **Saved** - User saved basket for later
3. **Abandoned** - Inactive for 24+ hours
4. **Converted** - Successfully converted to order

**State Transitions**:
- Active -> Saved: User clicks "Save for later"
- Active -> Abandoned: No activity for 24 hours
- Active -> Converted: Order placed successfully
- Saved -> Active: User resumes shopping
- Abandoned -> Active: User returns to cart

**Automated Cleanup**:
- Guest baskets: Delete after 30 days
- Abandoned baskets: Archive after 90 days
- Converted baskets: Retain for 1 year for analysis

## Environment Variables

```bash
# Application
APP_NAME=baskets-service
APP_ENV=local
APP_PORT=8002

# Database
DB_CONNECTION=mysql
DB_HOST=baskets-mysql
DB_PORT=3306
DB_DATABASE=baskets_db
DB_USERNAME=baskets_user
DB_PASSWORD=baskets_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=baskets_exchange
RABBITMQ_QUEUE=baskets_queue

# Service URLs
AUTH_SERVICE_URL=http://auth-service:8000
PRODUCTS_SERVICE_URL=http://products-service:8001

# Basket Configuration
BASKET_ABANDONMENT_HOURS=24
BASKET_CLEANUP_DAYS=30
MAX_BASKET_ITEMS=50
```

## Deployment

**Docker Configuration**:
```yaml
Service: baskets-service
Port Mapping: 8002:8000
Database: baskets-mysql (port 3309 external)
Depends On: baskets-mysql, rabbitmq, auth-service, products-service
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 2 replicas minimum
- CPU Request: 100m, Limit: 300m
- Memory Request: 256Mi, Limit: 512Mi
- Service Type: ClusterIP
- ConfigMap: Basket configuration
- CronJob: Abandoned basket detection (daily)

**Health Check Configuration**:
- Liveness Probe: GET /health (10s interval)
- Readiness Probe: Database connectivity
- Startup Probe: 30s timeout

## Performance Optimization

**Caching Strategy**:
- Active basket cached in Redis (user_id key)
- Promo code validation results cached (5 min TTL)
- Product price snapshots cached (2 min TTL)
- Basket calculations cached until item change

**Database Optimization**:
- Indexes on: user_id, session_id, type_id, abandoned_at
- Composite index for basket item lookups
- Soft deletes for baskets (retain history)

**Scheduled Jobs**:
- Abandoned basket detector (runs hourly)
- Guest basket cleanup (runs daily at 2 AM)
- Price sync with products service (runs every 30 min)

## Security Considerations

**Basket Access Control**:
- Users can only access their own baskets
- Session-based access for guest baskets
- Admin role required for basket management
- Promo code validation prevents enumeration attacks

**Input Validation**:
- Quantity limits (1-99 per item)
- Maximum basket items (50 items)
- Promo code format validation
- SQL injection protection (Eloquent ORM)

## Monitoring and Observability

**Metrics to Track**:
- Average basket size (items)
- Average basket value
- Basket abandonment rate
- Promo code usage rate
- Conversion rate (basket -> order)
- Price sync failures

**Logging**:
- Basket creation and conversion
- Item additions and removals
- Promo code applications
- Abandoned basket events
- Price sync operations

## Future Enhancements

- Guest basket to user basket merge on login
- Basket sharing functionality
- Wishlist to basket conversion
- Saved baskets with names
- Basket recommendations (complete the look)
- Cross-sell and upsell suggestions
- Real-time stock availability indicators
- Multi-currency support
- Gift wrapping options
- Subscription basket support
