# Questions Service

## Service Overview

**Purpose**: FAQ (Frequently Asked Questions) and Q&A system management.

**Port**: 8009
**Database**: questions_db (MySQL 8.0)
**External Port**: 3316 (for debugging)
**Dependencies**: Auth service (for protected operations)

## Responsibilities

- FAQ question and answer management
- Question categorization
- Public FAQ access
- Question search functionality
- Answer versioning
- Question analytics (views, helpfulness)
- Multi-language support (future)

## API Endpoints

### Health Check

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Service health check | No | {"status":"ok","service":"questions-service"} |
| GET | /status | Basic status check | No | {"status":"ok","database":"connected"} |

### Protected Endpoints (Auth Required)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /questions | List questions | Paginated question list |
| POST | /questions | Create question | {"title","body","category"} -> Created question |
| GET | /questions/{id} | Get question details | Full question with answers |
| PUT | /questions/{id} | Update question | Updated question |
| DELETE | /questions/{id} | Delete question | Success message |
| GET | /questions/{id}/answers | List answers | Answer list for question |
| POST | /questions/{id}/answers | Create answer | {"body"} -> Created answer |
| GET | /questions/{id}/answers/{answerId} | Get answer details | Answer object |
| PUT | /questions/{id}/answers/{answerId} | Update answer | Updated answer |
| DELETE | /questions/{id}/answers/{answerId} | Delete answer | Success message |

### Public Endpoints (No Auth)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /public/questions | Public FAQ list | Published questions only |
| GET | /public/questions/{id} | Public question view | Question with approved answers |
| GET | /public/questions/{id}/answers | Public answers | Approved answers only |
| GET | /public/questions/{id}/answers/{answerId} | Public answer view | Answer details |
| GET | /public/search | Search FAQ | {"q":"search term"} -> Matching questions |

## Database Schema

**Tables**:

1. **questions** - FAQ questions
   - id (PK)
   - user_id (FK, nullable - creator)
   - title
   - slug (unique)
   - body (text)
   - category (string, nullable - General, Product, Shipping, Payment, etc.)
   - tags (JSON)
   - status (enum: draft, published, archived)
   - view_count (integer, default 0)
   - helpful_count (integer, default 0)
   - not_helpful_count (integer, default 0)
   - order (integer - display order)
   - is_featured (boolean)
   - published_at (timestamp, nullable)
   - timestamps, soft_deletes

2. **answers** - Question answers
   - id (PK)
   - question_id (FK)
   - user_id (FK, nullable - author)
   - body (text)
   - status (enum: draft, published, archived)
   - is_accepted (boolean - marked as best answer)
   - helpful_count (integer, default 0)
   - not_helpful_count (integer, default 0)
   - order (integer - display order)
   - published_at (timestamp, nullable)
   - timestamps, soft_deletes

**Relationships**:
- Question -> User (belongs to, nullable)
- Question -> Answers (has many)
- Answer -> Question (belongs to)
- Answer -> User (belongs to, nullable)

## Question Categories

**Default Categories**:
- General: General inquiries
- Products: Product-related questions
- Shipping: Delivery and shipping
- Payment: Payment and billing
- Returns: Returns and refunds
- Account: Account management
- Technical: Technical support

## Search Functionality

**Search Features**:
- Full-text search on title and body
- Category filtering
- Tag filtering
- Status filtering (for admins)
- Sort by relevance, date, views, helpfulness

**Search Query Example**:
```
GET /public/search?q=shipping&category=Shipping
```

## RabbitMQ Integration

**Events Consumed**:
- `product.issue` - Auto-generate FAQ from common issues
- `support.ticket.resolved` - Suggest FAQ creation from ticket

**Events Published**:
- `question.created` - New question added
- `question.published` - Question made public
- `answer.published` - New answer published
- `question.viewed` - Question viewed (for analytics)

**Message Format Example**:
```json
{
  "event": "question.published",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "question_id": 123,
    "title": "How long does shipping take?",
    "category": "Shipping",
    "tags": ["delivery", "timeframe"]
  }
}
```

## Environment Variables

```bash
# Application
APP_NAME=questions-service
APP_ENV=local
APP_PORT=8009

# Database
DB_CONNECTION=mysql
DB_HOST=questions-mysql
DB_PORT=3306
DB_DATABASE=questions_db
DB_USERNAME=questions_user
DB_PASSWORD=questions_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=questions_exchange
RABBITMQ_QUEUE=questions_queue

# Service URLs
AUTH_SERVICE_URL=http://auth-service:8000
```

## Deployment

**Docker Configuration**:
```yaml
Service: questions-service
Port Mapping: 8009:8000
Database: questions-mysql (port 3316 external)
Depends On: questions-mysql, rabbitmq
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
- Published questions cached (10 min TTL)
- FAQ categories cached (30 min TTL)
- Search results cached by query (5 min TTL)
- Popular questions cached (15 min TTL)

**Database Optimization**:
- Indexes on: slug, category, status, is_featured
- Full-text search index on title and body
- Composite indexes for filtering queries

## Monitoring and Observability

**Metrics to Track**:
- Total questions
- Questions by category
- Average view count
- Search query performance
- Helpfulness ratings

**Logging**:
- Question/answer CRUD operations
- Search queries
- View tracking
- Helpfulness votes

## Future Enhancements

- Multi-language support
- Rich text editor for answers
- User voting system
- Related questions suggestions
- Answer approval workflow
- FAQ import/export
- Analytics dashboard
- AI-powered answer suggestions
