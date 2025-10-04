# Orders Service

## Service Overview

**Purpose**: Order processing and lifecycle management with state machine pattern for order status transitions.

**Port**: 8003
**Database**: orders_db (MySQL 8.0)
**External Port**: 3310 (for debugging)
**Dependencies**: Auth service, Baskets service (basket conversion), Products service (inventory), Deliveries service (shipping)

## Responsibilities

- Order creation from basket conversion
- Order lifecycle management (state machine)
- Order status tracking and transitions
- Order item management
- Order history and tracking
- Payment status tracking (integration point)
- Cancellation and refund handling
- Order statistics and reporting

## API Endpoints

### Public Endpoints

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Service health check | No | {"status":"healthy","service":"orders-service"} |
| GET | /debug | Simple debug endpoint | No | "Simple debug text" |
| GET | /order-status | List order statuses | No | [{"id","name","description"}] |
| GET | /order-status/{id} | Get status details | No | Status object |
| GET | /order-status/statistics | Status statistics | No | Status distribution data |

### User Endpoints (Auth Required - Temporarily Public for Testing)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /orders | List user's orders | Paginated order list |
| GET | /orders/{id} | Get order details | Full order with items and status |
| POST | /orders/create-from-basket | Create order from basket | {"basket_id","shipping_address_id","billing_address_id"} -> Created order |
| PUT | /orders/{id}/status | Update order status | {"status_id"} -> Updated order |
| PUT | /orders/{id}/cancel | Cancel order | Cancellation reason -> Success |

### Admin Endpoints (Temporarily Public for Testing)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /admin/orders | List all orders | Paginated with filters (status, date range, user) |
| GET | /admin/orders/{id} | Get order details | Full order object |
| PUT | /admin/orders/{id} | Update order | Order data -> Updated order |
| DELETE | /admin/orders/{id} | Delete order | Success message (soft delete) |
| POST | /admin/order-status | Create order status | Status data -> Created status |
| PUT | /admin/order-status/{id} | Update status | Updated status |
| DELETE | /admin/order-status/{id} | Delete status | Success message |

## Database Schema

**Tables**:

1. **orders** - Order headers
   - id (PK)
   - order_number (unique, generated)
   - user_id (FK)
   - basket_id (FK, nullable)
   - status_id (FK)
   - subtotal (decimal)
   - discount_amount (decimal)
   - tax_amount (decimal)
   - shipping_amount (decimal)
   - total (decimal)
   - currency (default: USD)
   - payment_method
   - payment_status (enum: pending, authorized, paid, failed, refunded)
   - payment_transaction_id
   - shipping_address_id (FK to addresses service)
   - billing_address_id (FK to addresses service)
   - customer_notes (text)
   - admin_notes (text)
   - ip_address
   - user_agent
   - cancelled_at (timestamp, nullable)
   - cancellation_reason (text, nullable)
   - timestamps, soft_deletes

2. **order_items** - Line items in orders
   - id (PK)
   - order_id (FK)
   - product_id (FK - referenced from products service)
   - quantity (integer)
   - unit_price (decimal - snapshot at order time)
   - total_price (decimal - calculated)
   - tax_amount (decimal)
   - discount_amount (decimal)
   - product_snapshot (JSON - product details at order time)
   - timestamps

3. **order_status** - Order states
   - id (PK)
   - name (pending, processing, confirmed, shipped, delivered, cancelled, refunded)
   - description
   - color (for UI display)
   - order (integer - display order)
   - is_cancellable (boolean)
   - is_final (boolean)
   - timestamps

**Relationships**:
- Order -> User (belongs to)
- Order -> Basket (belongs to, nullable)
- Order -> Status (belongs to)
- Order -> Items (has many)
- OrderItem -> Product (referenced via product_id)

## Order Status State Machine

**Status Flow**:
```
pending -> processing -> confirmed -> shipped -> delivered
   |
   +-> cancelled
   |
   +-> refunded (from any pre-delivery status)
```

**Status Definitions**:
- **pending**: Order placed, awaiting payment confirmation
- **processing**: Payment confirmed, preparing order
- **confirmed**: Order confirmed, ready for shipment
- **shipped**: Order shipped, in transit
- **delivered**: Order successfully delivered
- **cancelled**: Order cancelled (by customer or admin)
- **refunded**: Payment refunded

**Transition Rules**:
- Cannot cancel after shipped status
- Cannot modify order after processing starts
- Refund only allowed before delivery
- Status transitions logged for audit trail

## Order Creation Flow

1. User initiates checkout from basket
2. Basket service validates basket (stock availability, prices)
3. Order service creates order from basket
4. Product snapshots stored in order_items
5. Products service reduces inventory
6. Order status set to "pending"
7. Payment gateway integration (future)
8. On payment success: status -> "processing"
9. Deliveries service notified to create shipment
10. Order confirmation sent to user

## RabbitMQ Integration

**Events Consumed**:
- `basket.checkout.request` - Basket ready for conversion
- `payment.authorized` - Payment successful
- `payment.failed` - Payment failed
- `delivery.shipped` - Order shipped
- `delivery.delivered` - Order delivered

**Events Published**:
- `order.created` - New order placed
- `order.status.changed` - Status transition
- `order.cancelled` - Order cancellation
- `order.refunded` - Refund processed
- `inventory.reserve` - Reserve products (to products service)
- `inventory.release` - Release reserved products (on cancellation)
- `delivery.create.request` - Create shipment (to deliveries service)

**Message Format Example**:
```json
{
  "event": "order.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "order_id": 1001,
    "order_number": "ORD-20240115-1001",
    "user_id": 123,
    "total": 149.98,
    "items": [
      {"product_id": 789, "quantity": 2, "unit_price": 49.99},
      {"product_id": 790, "quantity": 1, "unit_price": 49.99}
    ],
    "shipping_address_id": 456,
    "billing_address_id": 457
  }
}
```

## Environment Variables

```bash
# Application
APP_NAME=orders-service
APP_ENV=local
APP_PORT=8003

# Database
DB_CONNECTION=mysql
DB_HOST=orders-mysql
DB_PORT=3306
DB_DATABASE=orders_db
DB_USERNAME=orders_user
DB_PASSWORD=orders_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=orders_exchange
RABBITMQ_QUEUE=orders_queue

# Service URLs
AUTH_SERVICE_URL=http://auth-service:8000
BASKETS_SERVICE_URL=http://baskets-service:8002
PRODUCTS_SERVICE_URL=http://products-service:8001
DELIVERIES_SERVICE_URL=http://deliveries-service:8005

# Order Configuration
ORDER_NUMBER_PREFIX=ORD
ORDER_CANCELLATION_WINDOW_HOURS=24
```

## Deployment

**Docker Configuration**:
```yaml
Service: orders-service
Port Mapping: 8003:8000
Database: orders-mysql (port 3310 external)
Depends On: orders-mysql, rabbitmq, baskets-service, products-service
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 3 replicas minimum (high availability)
- CPU Request: 200m, Limit: 500m
- Memory Request: 512Mi, Limit: 1Gi
- Service Type: ClusterIP
- ConfigMap: Order configuration
- Secret: Payment gateway credentials (future)

**Health Check Configuration**:
- Liveness Probe: GET /health (10s interval)
- Readiness Probe: Database connectivity
- Startup Probe: 30s timeout

## Performance Optimization

**Caching Strategy**:
- Order status list cached (never changes frequently)
- User order history cached (5 min TTL)
- Order details cached (2 min TTL)
- Statistics cached (15 min TTL)

**Database Optimization**:
- Indexes on: order_number, user_id, status_id, created_at
- Composite indexes for filtering queries
- Partitioning by date for historical orders
- Archive old orders (1+ year) to separate table

## Security Considerations

**Access Control**:
- Users can only view their own orders
- Admin role required for all orders access
- Order modification restricted after processing
- Cancellation window enforced

**Data Protection**:
- Sensitive payment data not stored
- PCI compliance for payment gateway integration
- Order history retention policy
- GDPR compliance for data deletion

## Monitoring and Observability

**Metrics to Track**:
- Order creation rate
- Average order value
- Order status distribution
- Cancellation rate
- Time in each status
- Payment success rate

**Logging**:
- All order creations
- Status transitions
- Cancellations and refunds
- Payment events
- Integration failures

## Future Enhancements

- Payment gateway integration (Stripe, PayPal)
- Split payments support
- Partial cancellations/refunds
- Order modification after placement
- Subscription orders
- Digital product fulfillment
- Order tracking notifications
- Invoice generation (PDF)
- Return management system
- Loyalty points integration
