# API Gateway Service

## Service Overview

**Purpose**: Single entry point for all client requests. Routes requests to appropriate microservices via RabbitMQ message broker.

**Port**: 8100
**Database**: None (stateless routing service)
**Dependencies**: RabbitMQ, all backend microservices

**Architecture Pattern**: API Gateway with asynchronous message-based routing

## Responsibilities

- Accept HTTP requests from clients (Nginx forwards to gateway)
- Route requests to appropriate backend services via RabbitMQ
- Coordinate service-to-service communication
- Provide service discovery and health monitoring
- Handle legacy authentication endpoints (backward compatibility)

## API Endpoints

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Gateway health check | No | {"status":"healthy","service":"api-gateway"} |
| GET | /simple-health | Text-based health probe | No | "healthy" (text/plain) |
| GET | /test-rabbitmq | RabbitMQ connection test | No | {"status":"success","rabbitmq_connected":true} |
| GET | /v1/services/status | List available services | No | {"services":[...]} |
| POST | /v1/login | Legacy direct login endpoint | No | JWT token response |
| ANY | /v1/{service}/{path} | Route to microservice | Varies | Service-specific response |
| GET | /services/status | Legacy service status | No | {"services":[...]} |
| POST | /login | Legacy login (backward compat) | No | JWT token response |
| ANY | /{service}/{path} | Legacy routing pattern | Varies | Service-specific response |

## Request Flow

1. Client sends HTTP request to Nginx (port 80/443)
2. Nginx forwards to API Gateway (port 8100)
3. Gateway validates request and determines target service
4. Gateway publishes message to RabbitMQ exchange
5. Target service consumes message from its queue
6. Service processes request and publishes response
7. Gateway receives response and returns to client

## RabbitMQ Integration

**Connection Configuration**:
- Host: rabbitmq container
- Port: 5672
- Virtual Host: /
- Credentials: guest/guest

**Message Pattern**:
- Gateway publishes to service-specific exchanges
- Each service has dedicated queue for requests
- Response-reply pattern for synchronous-like behavior
- Correlation IDs for request-response matching

**Routing Logic** (GatewayRouterService):
```
/v1/auth/... -> auth-service exchange
/v1/products/... -> products-service exchange
/v1/baskets/... -> baskets-service exchange
/v1/orders/... -> orders-service exchange
/v1/addresses/... -> addresses-service exchange
/v1/deliveries/... -> deliveries-service exchange
/v1/newsletters/... -> newsletters-service exchange
/v1/sav/... -> sav-service exchange
/v1/contacts/... -> contacts-service exchange
/v1/questions/... -> questions-service exchange
/v1/websites/... -> websites-service exchange
```

## Environment Variables

```bash
# Application
APP_NAME=api-gateway
APP_ENV=local
APP_PORT=8100

# RabbitMQ Configuration
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/

# Service URLs (for health checks and discovery)
AUTH_SERVICE_URL=http://auth-service:8000
PRODUCTS_SERVICE_URL=http://products-service:8001
BASKETS_SERVICE_URL=http://baskets-service:8002
ORDERS_SERVICE_URL=http://orders-service:8003
ADDRESSES_SERVICE_URL=http://addresses-service:8004
DELIVERIES_SERVICE_URL=http://deliveries-service:8005
NEWSLETTERS_SERVICE_URL=http://newsletters-service:8006
SAV_SERVICE_URL=http://sav-service:8007
CONTACTS_SERVICE_URL=http://contacts-service:8008
QUESTIONS_SERVICE_URL=http://questions-service:8009
WEBSITES_SERVICE_URL=http://websites-service:8010
MESSAGES_BROKER_URL=http://messages-broker:8011
```

## Deployment

**Docker Configuration**:
```yaml
Service: api-gateway
Port Mapping: 8100:8000
Depends On: rabbitmq, messages-broker
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 2 replicas minimum
- CPU Request: 100m, Limit: 200m
- Memory Request: 128Mi, Limit: 256Mi
- Service Type: ClusterIP
- Ingress: Routes /api/ and /v1/ paths

**Health Check Configuration**:
- Liveness Probe: GET /simple-health (5s interval, 3 failures)
- Readiness Probe: GET /health (10s interval)
- Startup Probe: GET /health (30s timeout)

## Key Components

**GatewayController**: Main routing controller
- Receives all incoming requests
- Determines target service
- Delegates to RabbitMQClientService

**GatewayRouterService**: Service discovery and routing logic
- Maps URL paths to service names
- Maintains service registry
- Provides available services list

**AuthController**: Legacy authentication endpoint
- Direct login support (backward compatibility)
- Will be deprecated once clients migrate to /v1/auth/login

**RabbitMQClientService** (Shared): Message broker client
- Publishes requests to service queues
- Handles response correlation
- Connection management and retry logic

## Performance Considerations

- Stateless design enables horizontal scaling
- RabbitMQ provides natural load balancing via queues
- Response timeout: 30 seconds (configurable)
- Connection pooling for RabbitMQ connections
- No database queries - pure routing logic

## Monitoring and Observability

**Metrics to Track**:
- Request rate per service
- Response time percentiles (p50, p95, p99)
- RabbitMQ connection health
- Failed routing attempts
- Service availability status

**Logging**:
- All requests logged with X-Request-ID header
- Service routing decisions
- RabbitMQ connection events
- Error and timeout events

## Future Enhancements

- Request rate limiting and throttling
- Circuit breaker pattern for failing services
- Request/response caching
- API versioning support
- GraphQL federation support
- WebSocket routing for real-time features
