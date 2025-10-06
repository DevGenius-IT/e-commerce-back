# Newsletters Service

## Service Overview

**Purpose**: Email newsletter subscription management and campaign execution with MinIO storage for email templates.

**Port**: 8006
**Database**: newsletters_db (MySQL 8.0)
**External Port**: 3313 (for debugging)
**Dependencies**: Auth service, MinIO (email template storage - bucket: newsletters)

## Responsibilities

- Newsletter subscription management
- Email campaign creation and execution
- Campaign scheduling and sending
- Email template management (MinIO storage)
- Subscriber list management
- Campaign analytics and tracking
- Email delivery webhooks
- Unsubscribe management

## API Endpoints

### Public Endpoints

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Service health check | No | {"status":"healthy","service":"newsletters-service"} |
| POST | /newsletters/subscribe | Subscribe to newsletter | No | {"email","first_name","last_name"} -> Success |
| GET | /newsletters/confirm/{token} | Confirm subscription | No | Confirmation success |
| GET | /newsletters/unsubscribe/{token} | Unsubscribe via email | No | Unsubscribe success |
| POST | /newsletters/unsubscribe/{token} | Unsubscribe POST | No | Unsubscribe success |

### Protected Endpoints (Auth Required)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /newsletters | List newsletters | Paginated subscriber list |
| GET | /newsletters/stats | Subscription statistics | Stats dashboard data |
| POST | /newsletters/bulk-import | Bulk import subscribers | CSV/Excel file -> Import results |
| GET | /newsletters/{id} | Get newsletter details | Newsletter object |
| PUT | /newsletters/{id} | Update newsletter | Updated newsletter |
| DELETE | /newsletters/{id} | Delete newsletter | Success message |
| GET | /campaigns | List campaigns | [{"id","name","status","stats"}] |
| POST | /campaigns | Create campaign | Campaign data -> Created campaign |
| GET | /campaigns/{id} | Get campaign details | Full campaign object |
| PUT | /campaigns/{id} | Update campaign | Updated campaign |
| DELETE | /campaigns/{id} | Delete campaign | Success message |
| POST | /campaigns/{id}/schedule | Schedule campaign | {"send_at"} -> Scheduled campaign |
| POST | /campaigns/{id}/send | Send campaign now | Campaign sent status |
| POST | /campaigns/{id}/cancel | Cancel scheduled campaign | Cancelled campaign |
| POST | /campaigns/{id}/test-send | Send test email | {"email"} -> Test sent |
| POST | /campaigns/{id}/duplicate | Duplicate campaign | Duplicated campaign |
| GET | /campaigns/{id}/stats | Campaign statistics | Opens, clicks, bounces, etc. |
| GET | /campaigns/{id}/analytics | Campaign analytics | Detailed analytics data |

### Admin Endpoints (Auth Required + Admin/Newsletter Manager Role)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /admin/system-stats | System statistics | Overall system health |
| GET | /admin/export/newsletters | Export newsletter data | CSV/Excel download |
| GET | /admin/export/campaigns | Export campaign data | CSV/Excel download |

### Webhook Endpoints (No Auth - IP/Secret Secured)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| POST | /webhooks/email-delivered | Email delivery webhook | Success acknowledgment |
| POST | /webhooks/email-opened | Email open tracking | Success acknowledgment |
| POST | /webhooks/email-clicked | Link click tracking | Success acknowledgment |
| POST | /webhooks/email-bounced | Bounce notification | Success acknowledgment |
| POST | /webhooks/email-complained | Spam complaint | Success acknowledgment |

## Database Schema

**Tables**:

1. **newsletters** - Newsletter subscribers
   - id (PK)
   - email (unique)
   - first_name
   - last_name
   - language (default: en)
   - status (enum: subscribed, unsubscribed, bounced, complained)
   - subscribed_at (timestamp)
   - unsubscribed_at (timestamp, nullable)
   - confirmation_token (unique)
   - confirmed_at (timestamp, nullable)
   - source (string - signup form, import, etc.)
   - tags (JSON)
   - custom_fields (JSON)
   - timestamps

2. **campaigns** - Email campaigns
   - id (PK)
   - name
   - subject
   - preview_text
   - from_name
   - from_email
   - reply_to (nullable)
   - template_id (FK, nullable)
   - html_content (text)
   - plain_text_content (text, nullable)
   - status (enum: draft, scheduled, sending, sent, cancelled)
   - scheduled_at (timestamp, nullable)
   - sent_at (timestamp, nullable)
   - total_recipients (integer)
   - total_sent (integer)
   - total_delivered (integer)
   - total_opened (integer)
   - total_clicked (integer)
   - total_bounced (integer)
   - total_complained (integer)
   - settings (JSON - tracking, unsubscribe link, etc.)
   - timestamps

3. **newsletter_campaigns** - Campaign-Subscriber mapping (many-to-many)
   - id (PK)
   - newsletter_id (FK)
   - campaign_id (FK)
   - sent_at (timestamp, nullable)
   - delivered_at (timestamp, nullable)
   - opened_at (timestamp, nullable)
   - clicked_at (timestamp, nullable)
   - bounced_at (timestamp, nullable)
   - bounce_reason (text, nullable)
   - unsubscribed_at (timestamp, nullable)
   - timestamps

4. **email_templates** - Reusable email templates
   - id (PK)
   - name
   - description
   - template_url (MinIO path)
   - thumbnail_url (MinIO path, nullable)
   - category
   - is_active (boolean)
   - timestamps

**Relationships**:
- Campaign -> Template (belongs to, nullable)
- Campaign -> Newsletters (many-to-many via newsletter_campaigns)
- Newsletter -> Campaigns (many-to-many via newsletter_campaigns)

## Newsletter Subscription Flow

1. User submits email via public form
2. Subscription record created with status "pending"
3. Confirmation email sent with unique token
4. User clicks confirmation link
5. Status updated to "subscribed"
6. Welcome email sent (optional)

## Campaign Lifecycle

**Campaign States**:
- **draft**: Being created/edited
- **scheduled**: Scheduled for future sending
- **sending**: Currently being sent
- **sent**: Completed
- **cancelled**: Cancelled before sending

**Send Process**:
1. Validate campaign (content, recipients)
2. Fetch active subscribers
3. Queue emails for sending (batch processing)
4. Track delivery, opens, clicks via webhooks
5. Update campaign statistics
6. Mark campaign as "sent"

## MinIO Integration

**Bucket**: newsletters
**Template Storage**:
```
newsletters/
  templates/
    campaign_123/
      template.html
      assets/
        image_1.jpg
        logo.png
    campaign_124/
      template.html
```

**Template Features**:
- HTML email templates with inline CSS
- Variable placeholders ({{first_name}}, {{unsubscribe_url}})
- Image hosting in MinIO
- Template versioning

## Email Tracking

**Tracking Mechanisms**:
- Open tracking: 1x1 pixel image
- Click tracking: Wrapped URLs with redirect
- Unsubscribe tracking: Unique token links

**Webhook Processing**:
- Real-time event processing
- Update campaign statistics
- Update subscriber engagement
- Trigger automated workflows (future)

## RabbitMQ Integration

**Events Consumed**:
- `user.registered` - Auto-subscribe to newsletter (optional)
- `order.completed` - Add to customer newsletter list

**Events Published**:
- `newsletter.subscribed` - New subscription
- `newsletter.unsubscribed` - Unsubscription
- `campaign.sent` - Campaign completed
- `email.bounced` - Hard bounce (for cleanup)

**Message Format Example**:
```json
{
  "event": "newsletter.subscribed",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "newsletter_id": 123,
    "email": "user@example.com",
    "source": "checkout_optin",
    "language": "en"
  }
}
```

## Environment Variables

```bash
# Application
APP_NAME=newsletters-service
APP_ENV=local
APP_PORT=8006

# Database
DB_CONNECTION=mysql
DB_HOST=newsletters-mysql
DB_PORT=3306
DB_DATABASE=newsletters_db
DB_USERNAME=newsletters_user
DB_PASSWORD=newsletters_password

# MinIO Configuration
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=admin
MINIO_SECRET_KEY=adminpass123
MINIO_BUCKET=newsletters
MINIO_REGION=us-east-1

# Email Service Provider (ESP) Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=E-Commerce Platform

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=newsletters_exchange
RABBITMQ_QUEUE=newsletters_queue

# Service URLs
AUTH_SERVICE_URL=http://auth-service:8000
```

## Deployment

**Docker Configuration**:
```yaml
Service: newsletters-service
Port Mapping: 8006:8000
Database: newsletters-mysql (port 3313 external)
Depends On: newsletters-mysql, rabbitmq, minio
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 2 replicas
- CPU Request: 150m, Limit: 400m
- Memory Request: 512Mi, Limit: 1Gi
- Service Type: ClusterIP
- ConfigMap: Email provider configuration
- Secret: ESP credentials
- CronJob: Scheduled campaign sender

**Health Check Configuration**:
- Liveness Probe: GET /health (10s interval)
- Readiness Probe: Database + MinIO connectivity
- Startup Probe: 30s timeout

## Performance Optimization

**Batch Processing**:
- Send emails in batches (100-500 per batch)
- Queue-based processing with Laravel queues
- Rate limiting to prevent ESP throttling
- Retry logic for failed sends

**Caching Strategy**:
- Subscriber lists cached (5 min TTL)
- Campaign statistics cached (2 min TTL)
- Template metadata cached (15 min TTL)

**Database Optimization**:
- Indexes on: email, status, subscribed_at
- Partitioning by campaign date
- Archive old campaigns (6+ months)

**Scheduled Jobs**:
- Scheduled campaign sender (every 5 minutes)
- Bounce cleanup (daily)
- Engagement scoring (weekly)
- List cleanup (monthly - remove inactive)

## Security and Compliance

**Privacy Compliance**:
- GDPR compliance (right to be forgotten)
- CAN-SPAM Act compliance (unsubscribe links)
- Double opt-in confirmation
- Data retention policies
- Export user data on request

**Security Measures**:
- Rate limiting on subscription endpoints
- Email validation and verification
- Webhook signature verification (future)
- DMARC/SPF/DKIM email authentication

## Monitoring and Observability

**Metrics to Track**:
- Subscriber growth rate
- Unsubscribe rate
- Email open rate
- Click-through rate
- Bounce rate
- Campaign send rate
- Queue processing speed

**Logging**:
- Subscription events
- Campaign sends
- Email delivery status
- Webhook events
- ESP API errors

## Future Enhancements

- A/B testing for campaigns
- Automated email workflows (drip campaigns)
- Segmentation and targeting
- Personalization engine
- Email template builder (drag-and-drop)
- RSS-to-email automation
- SMS campaign support
- Integration with major ESPs (SendGrid, Mailchimp, AWS SES)
- Advanced analytics dashboard
- Subscriber scoring and engagement tracking
