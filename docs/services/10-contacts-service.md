# Contacts Service

## Service Overview

**Purpose**: Contact management system for marketing, CRM, and email engagement tracking.

**Port**: 8008
**Database**: contacts_db (MySQL 8.0)
**External Port**: 3315 (for debugging)
**Dependencies**: Auth service

## Responsibilities

- Contact database management (CRM)
- Contact list organization and segmentation
- Contact tagging and categorization
- Subscription management (newsletter, marketing)
- Email engagement tracking (opens, clicks)
- Bulk contact operations
- Contact import/export
- Marketing automation integration point

## API Endpoints

### Health Check

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Service health check | No | {"status":"ok","service":"contacts-service"} |
| GET | /status | Basic status check | No | {"status":"ok","database":"connected"} |

### Protected Endpoints (Auth Required)

**Contacts Management**:

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /contacts | List all contacts | Paginated contact list with filters |
| POST | /contacts | Create contact | {"email","first_name","last_name"} -> Created contact |
| GET | /contacts/{id} | Get contact details | Full contact with engagement history |
| PUT | /contacts/{id} | Update contact | Updated contact |
| DELETE | /contacts/{id} | Delete contact | Success message |
| POST | /contacts/{id}/subscribe | Subscribe contact | {"type":"newsletter"} -> Updated contact |
| POST | /contacts/{id}/unsubscribe | Unsubscribe contact | {"type":"newsletter"} -> Updated contact |
| POST | /contacts/{id}/engagement | Record engagement | {"type":"opened","campaign_id"} -> Success |
| POST | /contacts/bulk-action | Bulk operations | {"action","contact_ids",[]} -> Results |

**Contact Lists Management**:

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /lists | List contact lists | [{"id","name","contact_count"}] |
| POST | /lists | Create list | {"name","description"} -> Created list |
| GET | /lists/{id} | Get list details | List with contacts |
| PUT | /lists/{id} | Update list | Updated list |
| DELETE | /lists/{id} | Delete list | Success message |
| POST | /lists/{id}/contacts | Add contacts to list | {"contact_ids":[]} -> Success |
| DELETE | /lists/{id}/contacts | Remove contacts | {"contact_ids":[]} -> Success |
| POST | /lists/{id}/sync | Sync contacts | {"contact_ids":[]} -> Synced list |
| POST | /lists/{id}/duplicate | Duplicate list | Duplicated list |
| GET | /lists/{id}/stats | List statistics | Engagement and growth stats |
| GET | /lists/{id}/export | Export contacts | CSV/Excel download |

**Contact Tags Management**:

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /tags | List all tags | [{"id","name","color","contact_count"}] |
| POST | /tags | Create tag | {"name","color"} -> Created tag |
| GET | /tags/popular | Get popular tags | Most used tags |
| GET | /tags/{id} | Get tag details | Tag with contacts |
| PUT | /tags/{id} | Update tag | Updated tag |
| DELETE | /tags/{id} | Delete tag | Success message |
| GET | /tags/{id}/contacts | Contacts with tag | Contact list |
| POST | /tags/{id}/apply | Apply tag | {"contact_ids":[]} -> Success |
| DELETE | /tags/{id}/remove | Remove tag | {"contact_ids":[]} -> Success |
| POST | /tags/{id}/merge | Merge tags | {"target_tag_id"} -> Merged tag |
| GET | /tags/{id}/stats | Tag statistics | Usage and engagement stats |

### Public Endpoints (No Auth)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| POST | /public/subscribe | Public subscription | {"email","first_name","newsletter":true} -> Success |
| POST | /public/unsubscribe | Public unsubscribe | {"email","type":"all"} -> Success |
| POST | /public/track/{contact}/opened | Track email open | Tracking pixel endpoint -> Success |
| POST | /public/track/{contact}/clicked | Track link click | Click tracking -> Success |

## Database Schema

**Tables**:

1. **contacts** - Contact records
   - id (PK)
   - email (unique)
   - first_name
   - last_name
   - company (nullable)
   - phone (nullable)
   - language (default: en)
   - timezone (nullable)
   - source (string - where contact came from)
   - status (enum: active, unsubscribed, bounced, complained)
   - newsletter_subscribed (boolean)
   - marketing_subscribed (boolean)
   - subscribed_at (timestamp, nullable)
   - unsubscribed_at (timestamp, nullable)
   - last_email_opened_at (timestamp, nullable)
   - last_email_clicked_at (timestamp, nullable)
   - total_emails_opened (integer, default 0)
   - total_emails_clicked (integer, default 0)
   - engagement_score (integer, nullable - calculated)
   - custom_fields (JSON)
   - timestamps, soft_deletes

2. **contact_lists** - Segmented lists
   - id (PK)
   - name
   - description (text, nullable)
   - type (enum: static, dynamic)
   - criteria (JSON, nullable - for dynamic lists)
   - contact_count (integer, default 0)
   - is_active (boolean)
   - timestamps

3. **contact_list_contacts** - List membership (many-to-many)
   - id (PK)
   - contact_list_id (FK)
   - contact_id (FK)
   - added_at (timestamp)
   - added_by (FK to users, nullable)

4. **contact_tags** - Tags for categorization
   - id (PK)
   - name (unique)
   - slug (unique)
   - color (hex color code)
   - description (text, nullable)
   - usage_count (integer, default 0)
   - timestamps

**Junction Tables**:
- **contact_tag** - Contact to Tag mapping (many-to-many)
  - contact_id (FK)
  - contact_tag_id (FK)
  - tagged_at (timestamp)

**Relationships**:
- Contact -> Lists (many-to-many via contact_list_contacts)
- Contact -> Tags (many-to-many via contact_tag)
- ContactList -> Contacts (many-to-many via contact_list_contacts)
- ContactTag -> Contacts (many-to-many via contact_tag)

## Contact Engagement Tracking

**Engagement Metrics**:
- Email opens (tracking pixel)
- Link clicks (wrapped URLs)
- Total engagement count
- Last engagement date
- Engagement score (calculated)

**Engagement Score Calculation**:
```
Score = (opens * 1) + (clicks * 3) + (recent_activity_bonus)
- 0-10: Low engagement
- 11-50: Medium engagement
- 51+: High engagement
```

**Engagement Events**:
- Email opened
- Link clicked
- Form submitted
- Purchase made (from orders service)

## List Types

**Static Lists**:
- Manually managed
- Contacts added/removed explicitly
- Used for specific campaigns

**Dynamic Lists** (Future):
- Auto-updated based on criteria
- Rule-based membership
- Examples: "Purchased in last 30 days", "High engagement score"

## Subscription Management

**Subscription Types**:
- Newsletter: Regular newsletters
- Marketing: Promotional emails
- Transactional: Order confirmations (always enabled)

**Unsubscribe Options**:
- Unsubscribe from specific type
- Unsubscribe from all marketing
- Update preferences

## RabbitMQ Integration

**Events Consumed**:
- `user.registered` - Create contact from user registration
- `order.completed` - Update contact with purchase data
- `newsletter.subscribed` - Sync newsletter subscription
- `email.sent` - Log email sent to contact

**Events Published**:
- `contact.created` - New contact added
- `contact.updated` - Contact information changed
- `contact.subscribed` - Subscription status changed
- `contact.unsubscribed` - Unsubscription event
- `contact.engagement` - Email engagement event
- `list.contact.added` - Contact added to list
- `tag.applied` - Tag applied to contact

**Message Format Example**:
```json
{
  "event": "contact.subscribed",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "contact_id": 123,
    "email": "user@example.com",
    "subscription_type": "newsletter",
    "source": "website_footer",
    "double_optin": true
  }
}
```

## Environment Variables

```bash
# Application
APP_NAME=contacts-service
APP_ENV=local
APP_PORT=8008

# Database
DB_CONNECTION=mysql
DB_HOST=contacts-mysql
DB_PORT=3306
DB_DATABASE=contacts_db
DB_USERNAME=contacts_user
DB_PASSWORD=contacts_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=contacts_exchange
RABBITMQ_QUEUE=contacts_queue

# Service URLs
AUTH_SERVICE_URL=http://auth-service:8000

# Contact Configuration
ENGAGEMENT_SCORE_OPEN_WEIGHT=1
ENGAGEMENT_SCORE_CLICK_WEIGHT=3
ENGAGEMENT_SCORE_PURCHASE_WEIGHT=10
```

## Deployment

**Docker Configuration**:
```yaml
Service: contacts-service
Port Mapping: 8008:8000
Database: contacts-mysql (port 3315 external)
Depends On: contacts-mysql, rabbitmq
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 2 replicas
- CPU Request: 100m, Limit: 300m
- Memory Request: 256Mi, Limit: 512Mi
- Service Type: ClusterIP

**Health Check Configuration**:
- Liveness Probe: GET /health (10s interval)
- Readiness Probe: Database connectivity
- Startup Probe: 30s timeout

## Performance Optimization

**Caching Strategy**:
- Contact lists cached (5 min TTL)
- Tag lists cached (10 min TTL)
- Popular tags cached (30 min TTL)
- Contact details cached (2 min TTL)

**Database Optimization**:
- Indexes on: email, status, engagement_score
- Full-text search on name and company
- Composite indexes for list membership queries
- Partitioning by created_at for large datasets

**Bulk Operations**:
- Batch processing for bulk actions
- Queue-based async processing
- Progress tracking for long operations

**Scheduled Jobs**:
- Engagement score recalculation (daily)
- Inactive contact cleanup (monthly)
- Dynamic list sync (hourly, future)
- List statistics update (hourly)

## Security and Compliance

**Privacy Compliance**:
- GDPR right to be forgotten
- Data export for users
- Consent management
- Unsubscribe link in all emails
- Double opt-in support

**Access Control**:
- Role-based permissions
- Admin-only bulk operations
- Audit log for sensitive operations

## Monitoring and Observability

**Metrics to Track**:
- Total contacts
- Subscription rate
- Unsubscribe rate
- Average engagement score
- List growth rate
- Tag usage distribution
- Bulk operation performance

**Logging**:
- Contact CRUD operations
- Subscription changes
- Bulk operations
- Engagement events
- Import/export operations

## Future Enhancements

- Advanced segmentation (dynamic lists)
- Contact scoring and lead qualification
- Duplicate contact detection and merging
- Contact lifecycle stages
- Marketing automation workflows
- Custom field builder
- API integrations (Salesforce, HubSpot)
- Progressive profiling
- Predictive analytics
- Contact enrichment services
