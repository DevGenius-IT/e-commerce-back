# SAV Service (Customer Support)

## Service Overview

**Purpose**: Customer support ticket management system (SAV = Service Après-Vente / After-Sales Service).

**Port**: 8007
**Database**: sav_db (MySQL 8.0)
**External Port**: 3314 (for debugging)
**Dependencies**: Auth service, MinIO (ticket attachment storage - bucket: sav)

**SAV** is French for "Service Après-Vente" which translates to "After-Sales Service" or Customer Support.

## Responsibilities

- Support ticket creation and management
- Ticket message/conversation threading
- File attachment handling (MinIO storage)
- Ticket assignment to support agents
- Ticket status tracking (open, assigned, resolved, closed)
- Ticket priority management
- Support ticket analytics
- Customer support history

## API Endpoints

### Health Check

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Service health check | No | {"status":"ok","service":"sav-service"} |
| GET | /status | Basic status check | No | {"status":"ok","database":"connected"} |

### Protected Endpoints (Auth Required)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /tickets | List user's tickets | Paginated ticket list |
| POST | /tickets | Create support ticket | {"subject","description","priority"} -> Created ticket |
| GET | /tickets/statistics | Ticket statistics | Dashboard data |
| GET | /tickets/{id} | Get ticket details | Full ticket with messages and attachments |
| PUT | /tickets/{id} | Update ticket | Updated ticket |
| DELETE | /tickets/{id} | Delete ticket | Success message |
| POST | /tickets/{id}/assign | Assign ticket | {"agent_id"} -> Assigned ticket |
| POST | /tickets/{id}/resolve | Resolve ticket | {"resolution"} -> Resolved ticket |
| POST | /tickets/{id}/close | Close ticket | Closed ticket |
| GET | /tickets/{ticketId}/messages | List ticket messages | Message thread |
| POST | /tickets/{ticketId}/messages | Add message | {"content","is_internal"} -> Created message |
| GET | /tickets/{ticketId}/messages/unread-count | Unread count | {"count":5} |
| POST | /tickets/{ticketId}/messages/mark-all-read | Mark all read | Success |
| GET | /tickets/{ticketId}/messages/{id} | Get message | Message object |
| PUT | /tickets/{ticketId}/messages/{id} | Update message | Updated message |
| DELETE | /tickets/{ticketId}/messages/{id} | Delete message | Success |
| POST | /tickets/{ticketId}/messages/{id}/mark-read | Mark as read | Success |
| GET | /tickets/{ticketId}/attachments | List attachments | Attachment list |
| POST | /tickets/{ticketId}/attachments | Upload attachment | File upload -> Attachment object |
| POST | /tickets/{ticketId}/attachments/multiple | Upload multiple | Files -> Attachment list |
| GET | /tickets/{ticketId}/attachments/{id} | Get attachment | Attachment metadata |
| GET | /tickets/{ticketId}/attachments/{id}/download | Download file | File download (presigned URL) |
| DELETE | /tickets/{ticketId}/attachments/{id} | Delete attachment | Success |
| GET | /tickets/{ticketId}/attachments/message/{messageId} | Get by message | Attachments for specific message |

### Public Endpoints (No Auth - For Anonymous Support Requests)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| POST | /public/tickets | Create public ticket | {"email","subject","description"} -> Ticket with access token |
| GET | /public/tickets/{ticketNumber} | Public ticket lookup | Ticket details (public messages only) |
| POST | /public/tickets/{ticketId}/messages | Public message | {"content"} -> Created message |

## Database Schema

**Tables**:

1. **support_tickets** - Support ticket headers
   - id (PK)
   - ticket_number (unique, generated - e.g., TICKET-20240115-001)
   - user_id (FK, nullable - for authenticated users)
   - assigned_to (FK, nullable - support agent user_id)
   - subject
   - description (text)
   - status (enum: open, assigned, in_progress, waiting_customer, resolved, closed)
   - priority (enum: low, normal, high, urgent)
   - category (string, nullable - billing, technical, product, etc.)
   - source (enum: web, email, phone, chat)
   - contact_email (for anonymous tickets)
   - contact_name (for anonymous tickets)
   - first_response_at (timestamp, nullable)
   - resolved_at (timestamp, nullable)
   - closed_at (timestamp, nullable)
   - resolution_notes (text, nullable)
   - internal_notes (text, nullable - staff only)
   - satisfaction_rating (integer, nullable - 1-5)
   - satisfaction_comment (text, nullable)
   - tags (JSON)
   - metadata (JSON)
   - timestamps, soft_deletes

2. **ticket_messages** - Conversation thread
   - id (PK)
   - ticket_id (FK)
   - user_id (FK, nullable)
   - author_name (string - for display)
   - author_email (string)
   - content (text)
   - is_internal (boolean - staff notes not visible to customer)
   - is_read (boolean)
   - read_at (timestamp, nullable)
   - timestamps

3. **ticket_attachments** - File attachments (MinIO)
   - id (PK)
   - ticket_id (FK)
   - message_id (FK, nullable - attachment linked to message)
   - user_id (FK, nullable)
   - file_name
   - file_path (MinIO path)
   - file_url (MinIO presigned URL)
   - file_type
   - file_size (bytes)
   - mime_type
   - timestamps

**Relationships**:
- Ticket -> User (belongs to, nullable)
- Ticket -> Assigned Agent (belongs to User, nullable)
- Ticket -> Messages (has many)
- Ticket -> Attachments (has many)
- Message -> Ticket (belongs to)
- Message -> Attachments (has many)
- Attachment -> Ticket (belongs to)
- Attachment -> Message (belongs to, nullable)

## Ticket Lifecycle

**Status Flow**:
```
open -> assigned -> in_progress -> waiting_customer -> resolved -> closed
   |        |            |                |
   +--------+------------+----------------+-> closed (can close from any status)
```

**Status Definitions**:
- **open**: New ticket, awaiting assignment
- **assigned**: Assigned to support agent
- **in_progress**: Agent actively working on ticket
- **waiting_customer**: Waiting for customer response
- **resolved**: Issue resolved, awaiting confirmation
- **closed**: Ticket closed (final state)

**Automatic Actions**:
- Auto-close resolved tickets after 7 days of inactivity
- Auto-escalate urgent tickets if no response in 2 hours
- Auto-assign based on workload distribution

## MinIO Integration

**Bucket**: sav
**Attachment Storage**:
```
sav/
  tickets/
    {ticket_id}/
      {attachment_id}/
        original_filename.ext
```

**File Handling**:
- Maximum file size: 10MB per file
- Allowed types: Images, PDFs, documents, logs
- Virus scanning before storage (future)
- Automatic file type validation
- Presigned URLs for secure downloads (1 hour expiration)

## Public Ticket Access

**Anonymous Ticket Creation**:
- No authentication required
- Email and name captured
- Ticket number returned for tracking
- Access via ticket number (no auth needed)

**Public Ticket Lookup**:
- Access using ticket number
- Only public messages visible
- Internal notes hidden
- Can reply via public endpoint

## RabbitMQ Integration

**Events Consumed**:
- `order.issue` - Create ticket from order problem
- `user.registered` - Link existing tickets to user account
- `product.issue_reported` - Create product-related ticket

**Events Published**:
- `ticket.created` - New ticket created
- `ticket.assigned` - Ticket assigned to agent
- `ticket.resolved` - Ticket marked as resolved
- `ticket.closed` - Ticket closed
- `ticket.message.new` - New message added
- `ticket.escalated` - Ticket escalated (urgent, no response)
- `ticket.satisfaction.received` - Customer satisfaction rating

**Message Format Example**:
```json
{
  "event": "ticket.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "ticket_id": 123,
    "ticket_number": "TICKET-20240115-123",
    "user_id": 456,
    "subject": "Order not received",
    "priority": "high",
    "category": "delivery"
  }
}
```

## Environment Variables

```bash
# Application
APP_NAME=sav-service
APP_ENV=local
APP_PORT=8007

# Database
DB_CONNECTION=mysql
DB_HOST=sav-mysql
DB_PORT=3306
DB_DATABASE=sav_db
DB_USERNAME=sav_user
DB_PASSWORD=sav_password

# MinIO Configuration
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=admin
MINIO_SECRET_KEY=adminpass123
MINIO_BUCKET=sav
MINIO_REGION=us-east-1

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=sav_exchange
RABBITMQ_QUEUE=sav_queue

# Service URLs
AUTH_SERVICE_URL=http://auth-service:8000

# Ticket Configuration
TICKET_NUMBER_PREFIX=TICKET
AUTO_CLOSE_RESOLVED_DAYS=7
ESCALATION_TIMEOUT_HOURS=2
MAX_ATTACHMENT_SIZE_MB=10
```

## Deployment

**Docker Configuration**:
```yaml
Service: sav-service
Port Mapping: 8007:8000
Database: sav-mysql (port 3314 external)
Depends On: sav-mysql, rabbitmq, minio
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 2 replicas
- CPU Request: 100m, Limit: 300m
- Memory Request: 256Mi, Limit: 512Mi
- Service Type: ClusterIP
- ConfigMap: Ticket configuration
- PVC: None (uses MinIO)

**Health Check Configuration**:
- Liveness Probe: GET /health (10s interval)
- Readiness Probe: Database + MinIO connectivity
- Startup Probe: 30s timeout

## Performance Optimization

**Caching Strategy**:
- User ticket list cached (3 min TTL)
- Ticket details cached (1 min TTL)
- Statistics cached (5 min TTL)
- Attachment metadata cached (10 min TTL)

**Database Optimization**:
- Indexes on: ticket_number, user_id, assigned_to, status, priority
- Full-text search on subject and description
- Composite indexes for filtering queries
- Soft deletes for ticket history

**Scheduled Jobs**:
- Auto-close resolved tickets (daily)
- Escalation monitoring (every 30 min)
- Satisfaction survey sender (after closure)
- Ticket metrics aggregation (hourly)

## Security Considerations

**Access Control**:
- Users only see their own tickets
- Support agents see assigned tickets + all open
- Admin sees all tickets
- Public tickets accessible by ticket number only

**Data Protection**:
- Attachment virus scanning (future)
- File type whitelist enforcement
- Size limits on uploads
- Internal notes never exposed to customers
- GDPR compliance for data deletion

## Monitoring and Observability

**Metrics to Track**:
- Average response time (first response)
- Average resolution time
- Tickets by status
- Tickets by priority
- Agent workload distribution
- Customer satisfaction scores
- Escalation rate

**Logging**:
- Ticket creation and closure
- Status transitions
- Assignment changes
- Message additions
- Attachment uploads
- Escalation events

## Future Enhancements

- Live chat integration
- Chatbot for common questions
- Knowledge base integration
- Canned responses for agents
- SLA tracking and enforcement
- Email-to-ticket conversion
- Multi-language support
- Customer satisfaction surveys
- Advanced reporting and analytics
- Integration with third-party helpdesk systems
- Video call support for complex issues
