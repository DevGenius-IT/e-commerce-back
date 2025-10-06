# Messages Broker Service

## Service Overview

**Purpose**: RabbitMQ message coordination and queue management service for inter-service communication.

**Port**: 8011
**Database**: messages_broker_db (MySQL 8.0, for failed message tracking)
**External Port**: 3318 (for debugging)
**Dependencies**: RabbitMQ (core dependency)

## Responsibilities

- RabbitMQ connection management and coordination
- Message publishing and consumption orchestration
- Failed message tracking and retry logic
- Queue health monitoring
- Dead letter queue (DLQ) management
- Message routing coordination
- Queue statistics and analytics

## API Endpoints

### Health Check

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /api/health | Service health check | No | {"status":"healthy","service":"messages-broker"} |

### Message Management

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| POST | /api/messages/publish | Publish message to queue | Yes | {"exchange","routing_key","message"} -> Success |
| GET | /api/messages/failed | List failed messages | Yes | Paginated failed message list |
| POST | /api/messages/retry/{id} | Retry failed message | Yes | Retry result |
| DELETE | /api/messages/failed/{id} | Delete failed message | Yes | Success message |

### Queue Management

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /api/queues | List all queues | Yes | [{"name","message_count","consumer_count"}] |
| GET | /api/queues/{queue}/stats | Queue statistics | Yes | Queue metrics and statistics |
| POST | /api/queues/{queue}/purge | Purge queue | Yes | Success message (use with caution) |

## Database Schema

**Tables**:

1. **failed_messages** - Failed message tracking
   - id (PK)
   - exchange
   - routing_key
   - payload (JSON)
   - error_message (text)
   - retry_count (integer, default 0)
   - max_retries (integer, default 3)
   - status (enum: pending, retrying, failed, resolved)
   - failed_at (timestamp)
   - last_retry_at (timestamp, nullable)
   - resolved_at (timestamp, nullable)
   - timestamps

2. **message_logs** - Message audit trail (optional)
   - id (PK)
   - message_id (unique)
   - exchange
   - routing_key
   - payload (JSON)
   - status (enum: published, delivered, failed)
   - published_at (timestamp)
   - delivered_at (timestamp, nullable)
   - metadata (JSON)
   - timestamps

## RabbitMQ Architecture

**Exchange Types**:
- **Direct Exchange**: Point-to-point routing
- **Topic Exchange**: Pattern-based routing
- **Fanout Exchange**: Broadcast to all queues

**Service Exchanges and Queues**:

```
auth_exchange -> auth_queue
products_exchange -> products_queue
baskets_exchange -> baskets_queue
orders_exchange -> orders_queue
addresses_exchange -> addresses_queue
deliveries_exchange -> deliveries_queue
newsletters_exchange -> newsletters_queue
sav_exchange -> sav_queue
contacts_exchange -> contacts_queue
questions_exchange -> questions_queue
websites_exchange -> websites_queue
```

**Dead Letter Exchange (DLX)**:
- Failed messages routed to DLQ
- Retry logic with exponential backoff
- Manual intervention for persistent failures

## Message Flow Coordination

**Publishing Flow**:
1. Service publishes message via RabbitMQClientService
2. Message routed to appropriate exchange
3. Exchange routes to target queue(s)
4. Consumers process messages
5. Acknowledgment or rejection

**Failure Handling**:
1. Message processing fails
2. Message rejected (nack)
3. Routed to dead letter queue
4. Logged in failed_messages table
5. Retry attempts with backoff
6. Manual resolution if max retries exceeded

## Message Patterns

**Request-Reply Pattern**:
```
Gateway -> Publish(request) -> Service Queue
Service -> Process -> Publish(response) -> Gateway Queue
Gateway -> Consume(response) -> Return to client
```

**Event Broadcasting**:
```
Service A -> Publish(event) -> Fanout Exchange
  -> Service B Queue
  -> Service C Queue
  -> Service D Queue
```

**Workflow Pattern**:
```
Order Created -> Inventory Check -> Payment Processing -> Shipping Creation
```

## Queue Configuration

**Standard Queue Settings**:
- Durable: true (persist across RabbitMQ restarts)
- Auto-delete: false
- Message TTL: 24 hours (configurable)
- Max length: 10000 messages (configurable)
- Overflow behavior: reject-publish

**Dead Letter Queue Settings**:
- TTL: 7 days
- Max retries: 3
- Retry delay: Exponential backoff (1min, 5min, 30min)

## Monitoring and Management

**Queue Metrics**:
- Message count per queue
- Consumer count
- Message rate (publish/deliver)
- Unacknowledged messages
- Failed message count

**Health Indicators**:
- RabbitMQ connection status
- Queue depth warnings (>80% capacity)
- Consumer lag
- Failed message rate

## Environment Variables

```bash
# Application
APP_NAME=messages-broker
APP_ENV=local
APP_PORT=8011

# Database
DB_CONNECTION=mysql
DB_HOST=messages-broker-mysql
DB_PORT=3306
DB_DATABASE=messages_broker_db
DB_USERNAME=messages_broker_user
DB_PASSWORD=messages_broker_password

# RabbitMQ Configuration
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_MANAGEMENT_PORT=15672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/

# Message Configuration
MESSAGE_TTL=86400000
MESSAGE_MAX_RETRIES=3
MESSAGE_RETRY_DELAY=60000
DLQ_TTL=604800000
```

## Deployment

**Docker Configuration**:
```yaml
Service: messages-broker
Port Mapping: 8011:8000
Database: messages-broker-mysql (port 3318 external)
Depends On: messages-broker-mysql, rabbitmq
Networks: e-commerce-network
Health Check: /api/health endpoint
```

**Kubernetes Resources**:
- Deployment: 2 replicas
- CPU Request: 100m, Limit: 300m
- Memory Request: 256Mi, Limit: 512Mi
- Service Type: ClusterIP
- ConfigMap: RabbitMQ configuration

**Health Check Configuration**:
- Liveness Probe: GET /api/health (10s interval)
- Readiness Probe: RabbitMQ connectivity
- Startup Probe: 30s timeout

## RabbitMQ Management

**Management UI**:
- URL: http://localhost:15672
- Credentials: guest/guest
- Features: Queue monitoring, message tracing, connection management

**CLI Management** (via docker exec):
```bash
docker exec rabbitmq rabbitmqctl list_queues
docker exec rabbitmq rabbitmqctl list_exchanges
docker exec rabbitmq rabbitmqctl list_bindings
```

## Performance Optimization

**Connection Pooling**:
- Reuse connections across services
- Channel pooling for publishing
- Connection recovery on failure

**Message Batching**:
- Batch publish when possible
- Batch acknowledgments
- Prefetch count optimization

**Consumer Optimization**:
- Parallel consumers per queue
- Prefetch limit tuning
- Consumer auto-scaling based on queue depth

## Failure Recovery

**Automatic Recovery**:
- Connection recovery with retry
- Channel recovery on errors
- Consumer recreation on failure

**Manual Intervention**:
- Failed message dashboard
- Manual retry trigger
- Message inspection tools
- Queue purging (emergency)

## Security Considerations

**Access Control**:
- RabbitMQ user permissions per service
- Exchange and queue permissions
- TLS encryption for connections (production)
- Management UI access restriction

**Message Security**:
- Message validation
- Payload size limits
- Rate limiting on publishing
- Poison message detection

## Monitoring and Observability

**Metrics to Track**:
- Message throughput (pub/sub)
- Queue depth per queue
- Consumer lag
- Failed message rate
- Connection count
- Memory usage

**Logging**:
- Message publishing events
- Consumption events
- Failed messages
- Connection errors
- Retry attempts

**Alerting**:
- Queue depth exceeds threshold
- Consumer lag warning
- Failed message spike
- Connection failures
- Memory pressure

## Future Enhancements

- Message replay capability
- Message transformation middleware
- Schema validation for messages
- Message versioning support
- Advanced routing rules
- Message encryption
- Priority queues
- Delayed message delivery
- Distributed tracing integration
- Kafka migration path (future scaling)

## Integration Guidelines

**For Service Developers**:

1. **Use Shared RabbitMQ Client**:
   - `Shared\Services\RabbitMQClientService`
   - Connection management handled
   - Retry logic included

2. **Message Format**:
```php
[
    'event' => 'event.name',
    'timestamp' => now()->toISOString(),
    'data' => [
        // Event-specific data
    ],
    'metadata' => [
        'service' => 'service-name',
        'version' => '1.0'
    ]
]
```

3. **Error Handling**:
   - Always ack/nack messages
   - Log failures
   - Don't block consumers
   - Implement idempotent processing

4. **Testing**:
   - Use test queues
   - Mock RabbitMQ in unit tests
   - Integration tests with real queues

## Troubleshooting

**Common Issues**:

1. **Messages not consumed**:
   - Check consumer is running
   - Verify queue bindings
   - Check message format

2. **High queue depth**:
   - Scale consumers
   - Check consumer performance
   - Investigate blocked messages

3. **Failed messages accumulating**:
   - Check error logs
   - Verify message format
   - Review consumer logic

4. **Connection failures**:
   - Check RabbitMQ status
   - Verify network connectivity
   - Review credentials
