# Deliveries Service

## Service Overview

**Purpose**: Delivery tracking, shipment management, and sale point (pickup location) management.

**Port**: 8005
**Database**: deliveries_db (MySQL 8.0)
**External Port**: 3312 (for debugging)
**Dependencies**: Auth service, Orders service (order fulfillment), Addresses service (shipping addresses)

## Responsibilities

- Delivery lifecycle management
- Shipment tracking
- Carrier integration coordination
- Sale point (pickup location) management
- Delivery status tracking
- Estimated delivery dates
- Real-time tracking updates
- Delivery statistics and analytics

## API Endpoints

### Public Endpoints

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Service health check | No | {"status":"healthy","service":"deliveries-service"} |
| GET | /debug | Debug endpoint | No | "Deliveries service debug endpoint" |
| GET | /deliveries/track/{trackingNumber} | Public tracking | No | Delivery status and history |
| GET | /sale-points | List sale points | No | [{"id","name","address","hours"}] |
| GET | /sale-points/{id} | Sale point details | No | Full sale point object |
| GET | /sale-points/nearby | Find nearby locations | No | Query params: lat, lng, radius |
| GET | /status | List delivery statuses | No | [{"id","name","description"}] |
| GET | /status/{id} | Status details | No | Status object |
| GET | /status/statistics | Status distribution | No | Delivery statistics by status |

### User Endpoints (Auth Required)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /deliveries | List user's deliveries | Paginated delivery list |
| GET | /deliveries/{id} | Get delivery details | Full delivery with tracking history |
| PUT | /deliveries/{id}/status | Update delivery status | {"status_id"} -> Updated delivery |
| POST | /deliveries/from-order | Create from order | {"order_id"} -> Created delivery |

### Admin Endpoints (Auth Required + Admin Role)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /admin/deliveries | List all deliveries | Paginated with filters |
| GET | /admin/deliveries/statistics | Delivery analytics | Statistics dashboard data |
| POST | /admin/deliveries | Create delivery | Delivery data -> Created delivery |
| GET | /admin/deliveries/{id} | Get delivery details | Full delivery object |
| PUT | /admin/deliveries/{id} | Update delivery | Updated delivery |
| DELETE | /admin/deliveries/{id} | Delete delivery | Success message |
| GET | /admin/sale-points | Manage sale points | Sale point list with stats |
| GET | /admin/sale-points/statistics | Sale point analytics | Usage statistics |
| POST | /admin/sale-points | Create sale point | Sale point data -> Created location |
| GET | /admin/sale-points/{id} | Sale point details | Full sale point object |
| PUT | /admin/sale-points/{id} | Update sale point | Updated sale point |
| DELETE | /admin/sale-points/{id} | Delete sale point | Success message |
| GET | /admin/status | Manage statuses | Status list |
| GET | /admin/status/statistics | Status analytics | Distribution data |
| POST | /admin/status | Create status | Status data -> Created status |
| GET | /admin/status/{id} | Status details | Status object |
| PUT | /admin/status/{id} | Update status | Updated status |
| DELETE | /admin/status/{id} | Delete status | Success message |

## Database Schema

**Tables**:

1. **deliveries** - Shipment records
   - id (PK)
   - order_id (FK - referenced from orders service)
   - user_id (FK)
   - tracking_number (unique, generated)
   - carrier_name (string - UPS, FedEx, USPS, DHL, etc.)
   - carrier_tracking_number (nullable - external tracking ID)
   - status_id (FK)
   - shipping_method (enum: standard, express, overnight, pickup)
   - sale_point_id (FK, nullable - for pickup deliveries)
   - shipping_address_id (FK - referenced from addresses service)
   - weight (decimal, nullable)
   - dimensions (JSON, nullable)
   - shipped_at (timestamp, nullable)
   - estimated_delivery_at (timestamp, nullable)
   - delivered_at (timestamp, nullable)
   - delivery_signature (string, nullable)
   - delivery_notes (text, nullable)
   - tracking_events (JSON - tracking history)
   - timestamps

2. **sale_points** - Pickup locations
   - id (PK)
   - name
   - code (unique)
   - type (enum: store, locker, partner)
   - address_line_1
   - address_line_2 (nullable)
   - city
   - region
   - postal_code
   - country
   - latitude (decimal)
   - longitude (decimal)
   - phone
   - email
   - opening_hours (JSON - schedule)
   - capacity (integer - max parcels)
   - current_load (integer - current parcels)
   - is_active (boolean)
   - features (JSON - has_parking, wheelchair_accessible, etc.)
   - timestamps

3. **status** - Delivery status states
   - id (PK)
   - name (pending, processing, in_transit, out_for_delivery, delivered, failed, returned)
   - description
   - color (for UI)
   - order (integer - display order)
   - is_final (boolean)
   - timestamps

**Relationships**:
- Delivery -> Order (belongs to, referenced)
- Delivery -> User (belongs to)
- Delivery -> Status (belongs to)
- Delivery -> SalePoint (belongs to, nullable)

## Delivery Status Flow

**Status Progression**:
```
pending -> processing -> in_transit -> out_for_delivery -> delivered
                            |
                            +-> failed -> returned
```

**Status Definitions**:
- **pending**: Delivery created, awaiting pickup
- **processing**: Package being prepared
- **in_transit**: Package in carrier network
- **out_for_delivery**: Out with courier for delivery
- **delivered**: Successfully delivered
- **failed**: Delivery attempt failed
- **returned**: Returned to sender

## Tracking Event History

**Tracking Events Structure**:
```json
[
  {
    "timestamp": "2024-01-15T10:00:00Z",
    "status": "in_transit",
    "location": "Distribution Center - City",
    "description": "Package arrived at distribution center",
    "carrier_event_code": "AR"
  },
  {
    "timestamp": "2024-01-15T14:30:00Z",
    "status": "out_for_delivery",
    "location": "Local Facility - City",
    "description": "Out for delivery",
    "carrier_event_code": "OFD"
  }
]
```

## Sale Point Features

**Nearby Location Search**:
- Geolocation-based search (radius in km)
- Filter by type, features, capacity
- Sort by distance from user location
- Real-time capacity information

**Operating Hours Format**:
```json
{
  "monday": {"open": "09:00", "close": "18:00"},
  "tuesday": {"open": "09:00", "close": "18:00"},
  "wednesday": {"open": "09:00", "close": "18:00"},
  "thursday": {"open": "09:00", "close": "18:00"},
  "friday": {"open": "09:00", "close": "20:00"},
  "saturday": {"open": "10:00", "close": "17:00"},
  "sunday": "closed"
}
```

## RabbitMQ Integration

**Events Consumed**:
- `order.confirmed` - Create delivery from order
- `order.cancelled` - Cancel associated delivery
- `carrier.tracking.update` - External tracking webhook

**Events Published**:
- `delivery.created` - New delivery created
- `delivery.status.changed` - Status updated
- `delivery.shipped` - Package shipped (to orders service)
- `delivery.delivered` - Package delivered (to orders service)
- `delivery.failed` - Delivery attempt failed
- `sale_point.capacity.warning` - Sale point near capacity

**Message Format Example**:
```json
{
  "event": "delivery.shipped",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "delivery_id": 789,
    "order_id": 456,
    "tracking_number": "DEL-20240115-789",
    "carrier": "UPS",
    "carrier_tracking_number": "1Z999AA10123456784",
    "estimated_delivery": "2024-01-18T17:00:00Z"
  }
}
```

## Carrier Integration

**Supported Carriers** (Integration Points):
- UPS
- FedEx
- USPS
- DHL
- Local courier services

**Carrier API Integration** (Future):
- Real-time rate quotes
- Label generation
- Automatic tracking updates
- Delivery confirmation
- Address validation

## Environment Variables

```bash
# Application
APP_NAME=deliveries-service
APP_ENV=local
APP_PORT=8005

# Database
DB_CONNECTION=mysql
DB_HOST=deliveries-mysql
DB_PORT=3306
DB_DATABASE=deliveries_db
DB_USERNAME=deliveries_user
DB_PASSWORD=deliveries_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=deliveries_exchange
RABBITMQ_QUEUE=deliveries_queue

# Service URLs
AUTH_SERVICE_URL=http://auth-service:8000
ORDERS_SERVICE_URL=http://orders-service:8003
ADDRESSES_SERVICE_URL=http://addresses-service:8004

# Delivery Configuration
TRACKING_NUMBER_PREFIX=DEL
DEFAULT_CARRIER=UPS
ESTIMATED_DELIVERY_DAYS=3
```

## Deployment

**Docker Configuration**:
```yaml
Service: deliveries-service
Port Mapping: 8005:8000
Database: deliveries-mysql (port 3312 external)
Depends On: deliveries-mysql, rabbitmq, orders-service
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 2 replicas
- CPU Request: 100m, Limit: 300m
- Memory Request: 256Mi, Limit: 512Mi
- Service Type: ClusterIP
- ConfigMap: Carrier configuration

**Health Check Configuration**:
- Liveness Probe: GET /health (10s interval)
- Readiness Probe: Database connectivity
- Startup Probe: 30s timeout

## Performance Optimization

**Caching Strategy**:
- Sale point list cached (10 min TTL)
- Delivery status list cached (never expires)
- User delivery history cached (3 min TTL)
- Nearby sale points cached by location (5 min TTL)

**Database Optimization**:
- Indexes on: tracking_number, order_id, user_id, status_id
- Geospatial index on sale_points (latitude, longitude)
- Partitioning by delivery date for historical data

**Scheduled Jobs**:
- Carrier tracking sync (every 30 min)
- Sale point capacity updates (hourly)
- Estimated delivery recalculation (daily)

## Monitoring and Observability

**Metrics to Track**:
- Deliveries by status
- Average delivery time
- On-time delivery rate
- Failed delivery rate
- Sale point utilization
- Carrier performance

**Logging**:
- Delivery creation and status changes
- Tracking updates
- Sale point capacity warnings
- Carrier API failures

## Future Enhancements

- Real-time GPS tracking for couriers
- Customer delivery time slot selection
- SMS/email delivery notifications
- Proof of delivery photos
- Re-delivery scheduling
- Return label generation
- Multi-package shipments
- International shipping support
- Customs documentation
- Shipping insurance integration
