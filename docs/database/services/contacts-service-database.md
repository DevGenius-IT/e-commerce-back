# Contacts Service Database Documentation

## Table of Contents
- [Overview](#overview)
- [Database Information](#database-information)
- [Entity Relationship Diagram](#entity-relationship-diagram)
- [Table Schemas](#table-schemas)
- [CRM System Design](#crm-system-design)
- [List Management and Segmentation](#list-management-and-segmentation)
- [Tag System](#tag-system)
- [Events Published](#events-published)
- [Cross-Service References](#cross-service-references)
- [Indexes and Performance](#indexes-and-performance)
- [Import and Export Operations](#import-and-export-operations)
- [RBAC Integration](#rbac-integration)
- [Backup and Maintenance](#backup-and-maintenance)

## Overview

The contacts-service database (`contacts_service_db`) provides comprehensive CRM (Customer Relationship Management) functionality for managing customer contacts, organizing them into lists, and categorizing them with tags. This service enables marketing campaigns, customer segmentation, and contact relationship management.

**Service:** contacts-service
**Database:** contacts_service_db
**External Port:** 3323
**Total Tables:** 8 (4 core, 4 Laravel infrastructure)

**Key Capabilities:**
- Contact information management with company details
- List-based contact organization and segmentation
- Tag-based categorization system
- Bulk import/export operations
- Contact relationship tracking
- Event-driven synchronization with other services
- Role-based access control via Spatie Permission

## Database Information

### Connection Details
```bash
Host: localhost (in Docker network: contacts-mysql)
Port: 3323 (external), 3306 (internal)
Database: contacts_service_db
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
Engine: InnoDB
```

### Environment Configuration
```bash
DB_CONNECTION=mysql
DB_HOST=contacts-mysql
DB_PORT=3306
DB_DATABASE=contacts_service_db
DB_USERNAME=contacts_user
DB_PASSWORD=contacts_password
```

## Entity Relationship Diagram

```mermaid
erDiagram
    contact_lists ||--o{ contact_list_contacts : "contains"
    contacts ||--o{ contact_list_contacts : "belongs to lists"
    contact_tags ||--o{ contact_tag_pivot : "tagged on contacts"
    contacts ||--o{ contact_tag_pivot : "has tags"
    users ||--o{ contacts : "manages"
    roles ||--o{ model_has_roles : "assigned to users"
    permissions ||--o{ model_has_permissions : "granted to users"

    contact_lists {
        bigint id PK
        string name UK
        text description
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    contacts {
        bigint id PK
        string email UK
        string first_name
        string last_name
        string company
        string phone
        string country
        string city
        timestamp created_at
        timestamp updated_at
    }

    contact_tags {
        bigint id PK
        string name UK
        string color
        timestamp created_at
        timestamp updated_at
    }

    contact_list_contacts {
        bigint contact_list_id PK_FK
        bigint contact_id PK_FK
        timestamp added_at
    }

    contact_tag_pivot {
        bigint contact_id PK_FK
        bigint contact_tag_id PK_FK
        timestamp created_at
    }

    users {
        bigint id PK
        string email UK
        string firstname
        string lastname
        timestamp created_at
        timestamp updated_at
    }

    roles {
        bigint id PK
        string name UK
        string guard_name
    }

    permissions {
        bigint id PK
        string name UK
        string guard_name
    }

    model_has_roles {
        bigint role_id PK_FK
        string model_type PK
        bigint model_id PK
    }

    model_has_permissions {
        bigint permission_id PK_FK
        string model_type PK
        bigint model_id PK
    }

    jobs {
        bigint id PK
        string queue
        longtext payload
        tinyint attempts
    }

    cache {
        string key PK
        mediumtext value
        int expiration
    }

    cache_locks {
        string key PK
        string owner
        int expiration
    }
```

## Table Schemas

### Core Tables

#### 1. contact_lists
Contact list management for segmentation and campaign targeting.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | List identifier |
| name | VARCHAR(255) | NOT NULL, UNIQUE | List name (unique across system) |
| description | TEXT | NULLABLE | List purpose and description |
| is_active | BOOLEAN | NOT NULL, DEFAULT TRUE | Active/archived status |
| created_at | TIMESTAMP | NULLABLE | List creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
```sql
PRIMARY KEY (id)
UNIQUE KEY contact_lists_name_unique (name)
INDEX contact_lists_is_active_index (is_active)
INDEX contact_lists_created_at_index (created_at)
```

**Business Rules:**
- List names must be unique across entire system
- is_active flag allows archiving without deletion
- Description supports markdown formatting
- Lists can be empty (no contacts)
- Many-to-many with contacts via contact_list_contacts

**Model Features:**
```php
// Model: ContactList.php
$fillable = ['name', 'description', 'is_active'];

$casts = [
    'is_active' => 'boolean',
    'created_at' => 'datetime',
    'updated_at' => 'datetime'
];

// Relationships
public function contacts()
{
    return $this->belongsToMany(Contact::class, 'contact_list_contacts')
                ->withPivot('added_at')
                ->withTimestamps();
}
```

**Sample Data:**
```json
{
  "id": 1,
  "name": "Newsletter Subscribers",
  "description": "Customers who opted in for weekly newsletter",
  "is_active": true,
  "created_at": "2025-10-03T10:00:00Z",
  "updated_at": "2025-10-03T10:00:00Z"
}
```

**Common Lists:**
- "All Customers" - Complete customer base
- "Newsletter Subscribers" - Marketing opt-ins
- "VIP Customers" - High-value customers
- "Inactive Users" - No activity in 90+ days
- "Seasonal Campaign 2024" - Time-limited campaign
- "Product Launch Prospects" - Pre-launch interest list

---

#### 2. contacts
Core contact entity with personal and company information.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Contact identifier |
| email | VARCHAR(255) | NOT NULL, UNIQUE | Contact email address |
| first_name | VARCHAR(255) | NOT NULL | Contact first name |
| last_name | VARCHAR(255) | NOT NULL | Contact last name |
| company | VARCHAR(255) | NULLABLE | Company/organization name |
| phone | VARCHAR(255) | NULLABLE | Contact phone number |
| country | VARCHAR(255) | NULLABLE | Country of residence |
| city | VARCHAR(255) | NULLABLE | City of residence |
| created_at | TIMESTAMP | NULLABLE | Contact creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
```sql
PRIMARY KEY (id)
UNIQUE KEY contacts_email_unique (email)
INDEX contacts_first_name_index (first_name)
INDEX contacts_last_name_index (last_name)
INDEX contacts_company_index (company)
INDEX contacts_country_index (country)
INDEX contacts_city_index (city)
INDEX contacts_created_at_index (created_at)
```

**Business Rules:**
- Email must be unique (primary identifier)
- first_name and last_name are required
- Company field for B2B contacts
- Phone stored as string (supports international formats)
- Country and city for geographic segmentation
- No soft deletes (permanent contact records)

**Model Features:**
```php
// Model: Contact.php
$fillable = [
    'email',
    'first_name',
    'last_name',
    'company',
    'phone',
    'country',
    'city'
];

$casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime'
];

// Relationships
public function lists()
{
    return $this->belongsToMany(ContactList::class, 'contact_list_contacts')
                ->withPivot('added_at')
                ->withTimestamps();
}

public function tags()
{
    return $this->belongsToMany(ContactTag::class, 'contact_tag_pivot')
                ->withTimestamps();
}

// Scopes
public function scopeInCountry($query, $country)
{
    return $query->where('country', $country);
}

public function scopeInCity($query, $city)
{
    return $query->where('city', $city);
}

public function scopeWithCompany($query)
{
    return $query->whereNotNull('company');
}
```

**Sample Data:**
```json
{
  "id": 123,
  "email": "john.doe@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "company": "Acme Corporation",
  "phone": "+33612345678",
  "country": "France",
  "city": "Paris",
  "created_at": "2025-10-03T10:30:00Z",
  "updated_at": "2025-10-03T10:30:00Z"
}
```

**Validation Rules:**
```php
// ContactRequest.php
public function rules(): array
{
    return [
        'email' => ['required', 'email', 'max:255', 'unique:contacts,email'],
        'first_name' => ['required', 'string', 'max:255'],
        'last_name' => ['required', 'string', 'max:255'],
        'company' => ['nullable', 'string', 'max:255'],
        'phone' => ['nullable', 'string', 'max:255'],
        'country' => ['nullable', 'string', 'max:255'],
        'city' => ['nullable', 'string', 'max:255']
    ];
}
```

---

#### 3. contact_tags
Tag system for flexible contact categorization.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Tag identifier |
| name | VARCHAR(255) | NOT NULL, UNIQUE | Tag name (unique) |
| color | VARCHAR(255) | NULLABLE | Hex color code for UI display |
| created_at | TIMESTAMP | NULLABLE | Tag creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
```sql
PRIMARY KEY (id)
UNIQUE KEY contact_tags_name_unique (name)
INDEX contact_tags_created_at_index (created_at)
```

**Business Rules:**
- Tag names must be unique
- Color stored as hex code (e.g., "#FF5733")
- Tags can be applied to multiple contacts
- Many-to-many with contacts via contact_tag_pivot
- No hierarchy (flat structure)

**Model Features:**
```php
// Model: ContactTag.php
$fillable = ['name', 'color'];

$casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime'
];

// Relationships
public function contacts()
{
    return $this->belongsToMany(Contact::class, 'contact_tag_pivot')
                ->withTimestamps();
}

// Accessor for color with default
public function getColorAttribute($value)
{
    return $value ?? '#6B7280'; // Default gray if not set
}
```

**Sample Data:**
```json
{
  "id": 5,
  "name": "VIP",
  "color": "#FFD700",
  "created_at": "2025-10-03T09:00:00Z",
  "updated_at": "2025-10-03T09:00:00Z"
}
```

**Common Tags:**
- "VIP" - High-value customers (#FFD700 gold)
- "Lead" - Potential customers (#3B82F6 blue)
- "Inactive" - No recent activity (#EF4444 red)
- "Newsletter" - Newsletter subscribers (#10B981 green)
- "B2B" - Business customers (#8B5CF6 purple)
- "Support" - Active support cases (#F59E0B amber)
- "Partner" - Business partners (#EC4899 pink)

---

#### 4. contact_list_contacts (Pivot)
Many-to-many relationship between contacts and lists.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| contact_list_id | BIGINT UNSIGNED | PK, FK | List reference (contact_lists.id) |
| contact_id | BIGINT UNSIGNED | PK, FK | Contact reference (contacts.id) |
| added_at | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Timestamp when contact added to list |

**Foreign Keys:**
```sql
FOREIGN KEY (contact_list_id) REFERENCES contact_lists(id) ON DELETE CASCADE
FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
```

**Indexes:**
```sql
PRIMARY KEY (contact_list_id, contact_id)
INDEX contact_list_contacts_contact_id_index (contact_id)
INDEX contact_list_contacts_added_at_index (added_at)
```

**Business Rules:**
- Composite primary key prevents duplicate entries
- Cascade delete: removing contact or list removes relationship
- added_at tracks when contact joined list
- One contact can be in multiple lists
- One list can contain multiple contacts

**Usage Queries:**
```sql
-- Get all contacts in a list
SELECT c.*
FROM contacts c
INNER JOIN contact_list_contacts clc ON c.id = clc.contact_id
WHERE clc.contact_list_id = 1
ORDER BY clc.added_at DESC;

-- Get all lists for a contact
SELECT cl.*
FROM contact_lists cl
INNER JOIN contact_list_contacts clc ON cl.id = clc.contact_list_id
WHERE clc.contact_id = 123
AND cl.is_active = 1;

-- Recently added contacts to list
SELECT c.*, clc.added_at
FROM contacts c
INNER JOIN contact_list_contacts clc ON c.id = clc.contact_id
WHERE clc.contact_list_id = 1
AND clc.added_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY clc.added_at DESC;
```

---

### Laravel Infrastructure Tables

#### 5. users
Local user cache synchronized from auth-service.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK | User identifier (from auth-service) |
| email | VARCHAR(255) | NOT NULL, UNIQUE | User email |
| firstname | VARCHAR(255) | NULLABLE | User first name |
| lastname | VARCHAR(255) | NULLABLE | User last name |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last sync timestamp |

**Indexes:**
```sql
PRIMARY KEY (id)
UNIQUE KEY users_email_unique (email)
```

**Business Rules:**
- Synchronized from auth-service via RabbitMQ events
- id matches auth-service user ID
- Used for contact ownership and audit trails
- No local authentication (JWT validated via auth-service)

---

#### 6. roles and permissions
Spatie Laravel Permission tables for RBAC.

**roles:**
```sql
CREATE TABLE roles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY roles_name_guard_name_unique (name, guard_name)
);
```

**permissions:**
```sql
CREATE TABLE permissions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)
);
```

**model_has_roles and model_has_permissions:**
Polymorphic pivot tables for assigning roles/permissions to users.

**Standard Permissions:**
```sql
INSERT INTO permissions (name, guard_name) VALUES
('contacts.view', 'api'),       -- View contacts
('contacts.create', 'api'),     -- Create new contacts
('contacts.update', 'api'),     -- Update contact info
('contacts.delete', 'api'),     -- Delete contacts
('lists.manage', 'api'),        -- Manage contact lists
('tags.manage', 'api'),         -- Manage tags
('contacts.export', 'api'),     -- Export contacts
('contacts.import', 'api');     -- Import contacts
```

**Standard Roles:**
```sql
INSERT INTO roles (name, guard_name) VALUES
('contacts_manager', 'api'),    -- Full contact management
('contacts_viewer', 'api'),     -- Read-only access
('marketing_manager', 'api');   -- List and campaign management
```

---

#### 7. jobs
Queue jobs for asynchronous operations.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Job identifier |
| queue | VARCHAR(255) | NOT NULL, INDEXED | Queue name |
| payload | LONGTEXT | NOT NULL | Serialized job data |
| attempts | TINYINT UNSIGNED | NOT NULL | Execution attempts |
| reserved_at | INT UNSIGNED | NULLABLE | Reservation timestamp |
| available_at | INT UNSIGNED | NOT NULL | Availability timestamp |
| created_at | INT UNSIGNED | NOT NULL | Creation timestamp |

**Common Queued Jobs:**
- Contact import processing (CSV/Excel)
- Contact export generation
- Bulk list operations (add/remove multiple contacts)
- Contact data validation and enrichment
- Synchronization with external CRM systems

---

#### 8. cache and cache_locks
Laravel cache tables for performance optimization.

**cache:**
- Stores frequently accessed contact lists
- Caches tag associations
- Rate limiting for import/export operations

**cache_locks:**
- Prevents concurrent import operations
- Locks during bulk list updates

---

## CRM System Design

### Contact Management Philosophy

The contacts-service implements a flexible CRM system designed for:

1. **Multi-Source Contact Creation:**
   - Manual entry via admin interface
   - Bulk import from CSV/Excel files
   - Automated sync from user registrations (auth-service)
   - API integration from external systems
   - Contact form submissions (contacts-service)

2. **Segmentation Strategy:**
   - **Lists**: Membership-based grouping (campaigns, newsletters)
   - **Tags**: Attribute-based categorization (VIP, Lead, Inactive)
   - **Filters**: Dynamic queries (country, company, date ranges)

3. **Contact Lifecycle:**
   ```
   Creation → Enrichment → Segmentation → Engagement → Analysis
   ```

### Data Flow Architecture

```
┌──────────────────────────────────────────────────────┐
│                   Contact Sources                    │
└──────────────────────────────────────────────────────┘
           │
           ├─ Manual Entry (Admin UI)
           ├─ Bulk Import (CSV/Excel)
           ├─ User Registration (auth-service event)
           ├─ API Integration (External CRM)
           └─ Contact Forms (contacts-service)
           │
           ▼
┌──────────────────────────────────────────────────────┐
│              contacts_service_db                     │
│                   (contacts table)                   │
└──────────────────────────────────────────────────────┘
           │
           ├─ List Assignment (contact_list_contacts)
           ├─ Tag Application (contact_tag_pivot)
           └─ Event Publication (RabbitMQ)
           │
           ▼
┌──────────────────────────────────────────────────────┐
│                  Consumer Services                   │
└──────────────────────────────────────────────────────┘
           │
           ├─ newsletters-service (Email campaigns)
           ├─ marketing-service (Targeted ads) [future]
           └─ analytics-service (Reporting) [future]
```

## List Management and Segmentation

### List Types and Use Cases

#### 1. Static Lists
Manually curated contact collections.

**Characteristics:**
- Fixed membership
- Manual contact addition/removal
- Explicit list management

**Examples:**
```sql
-- Create static list
INSERT INTO contact_lists (name, description, is_active)
VALUES ('VIP Customers', 'High-value customer segment', 1);

-- Add contacts manually
INSERT INTO contact_list_contacts (contact_list_id, contact_id, added_at)
SELECT 5, id, NOW()
FROM contacts
WHERE id IN (10, 15, 23, 47, 89);
```

**Use Cases:**
- Event attendee lists
- Beta tester groups
- Partner contact lists
- Customer advisory boards

---

#### 2. Dynamic Lists (Query-Based)
Lists populated by saved filter criteria.

**Implementation:**
```php
// Dynamic list query stored in list description as JSON
$listQuery = [
    'country' => 'France',
    'city' => 'Paris',
    'created_after' => '2024-01-01',
    'tags' => ['VIP']
];

// Execute dynamic query
$contacts = Contact::query()
    ->where('country', $listQuery['country'])
    ->where('city', $listQuery['city'])
    ->where('created_at', '>=', $listQuery['created_after'])
    ->whereHas('tags', function($q) use ($listQuery) {
        $q->whereIn('name', $listQuery['tags']);
    })
    ->get();

// Sync to list
$list->contacts()->sync($contacts->pluck('id'));
```

**Use Cases:**
- Geographic targeting campaigns
- Time-based segmentation (new customers, inactive users)
- Behavior-based lists (purchases, support interactions)
- Multi-criteria filtering

---

#### 3. Campaign Lists
Time-limited lists for marketing campaigns.

**Lifecycle:**
```
Creation → Population → Campaign Execution → Analysis → Archive
```

**Example:**
```sql
-- Create campaign list
INSERT INTO contact_lists (name, description, is_active)
VALUES (
    'Q4 2024 Product Launch',
    'Contacts targeted for Q4 product launch campaign',
    1
);

-- Archive after campaign
UPDATE contact_lists
SET is_active = 0
WHERE name = 'Q4 2024 Product Launch'
AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

### List Operations

#### Bulk Add Contacts
```php
// Add multiple contacts to list
$list->contacts()->attach([101, 102, 103, 104], [
    'added_at' => now()
]);
```

#### Bulk Remove Contacts
```php
// Remove multiple contacts from list
$list->contacts()->detach([101, 102]);
```

#### Copy List
```php
// Duplicate list with all contacts
$newList = ContactList::create([
    'name' => $originalList->name . ' (Copy)',
    'description' => $originalList->description,
    'is_active' => true
]);

$contactIds = $originalList->contacts()->pluck('contact_id')->toArray();
$newList->contacts()->attach($contactIds, ['added_at' => now()]);
```

#### Merge Lists
```php
// Merge multiple lists into one
$targetList = ContactList::find(1);
$sourceLists = ContactList::whereIn('id', [2, 3, 4])->get();

foreach ($sourceLists as $sourceList) {
    $contactIds = $sourceList->contacts()
        ->whereNotIn('contact_id',
            $targetList->contacts()->pluck('contact_id')
        )
        ->pluck('contact_id')
        ->toArray();

    $targetList->contacts()->attach($contactIds, ['added_at' => now()]);
}
```

#### List Intersection
```php
// Find contacts in multiple lists
$listIds = [1, 2, 3];
$minOccurrences = 2; // Contact must be in at least 2 lists

$contacts = DB::table('contact_list_contacts')
    ->select('contact_id')
    ->whereIn('contact_list_id', $listIds)
    ->groupBy('contact_id')
    ->havingRaw('COUNT(*) >= ?', [$minOccurrences])
    ->pluck('contact_id');
```

---

## Tag System

### Tag Design Philosophy

Tags provide flexible, user-defined categorization orthogonal to list membership:

- **Lists** = "Where" (which campaigns, which segments)
- **Tags** = "What" (characteristics, status, categories)

### Tag Categories (Organizational Convention)

#### 1. Status Tags
Current state of contact relationship.

**Examples:**
- `Active` - Currently engaged
- `Inactive` - No recent activity
- `Prospect` - Potential customer
- `Customer` - Active customer
- `Former Customer` - Churned customer

---

#### 2. Segment Tags
Customer classification.

**Examples:**
- `VIP` - High-value customer
- `Enterprise` - Large business customer
- `SMB` - Small/medium business
- `Individual` - Consumer customer

---

#### 3. Channel Tags
Communication preferences and sources.

**Examples:**
- `Email Opt-in` - Newsletter subscriber
- `SMS Opt-in` - SMS marketing consent
- `Social Media` - Social media follower
- `Webinar Attendee` - Event participant

---

#### 4. Behavior Tags
Activity-based categorization.

**Examples:**
- `Frequent Buyer` - High purchase frequency
- `One-Time Buyer` - Single purchase only
- `Support User` - Active support engagement
- `Product Advocate` - NPS promoter

---

### Tag Operations

#### Apply Tag to Contact
```php
// Single tag
$contact->tags()->attach($tagId, ['created_at' => now()]);

// Multiple tags
$contact->tags()->attach([1, 2, 3], ['created_at' => now()]);
```

#### Remove Tag from Contact
```php
// Single tag
$contact->tags()->detach($tagId);

// All tags
$contact->tags()->detach();
```

#### Find Contacts by Tag
```sql
-- Contacts with specific tag
SELECT c.*
FROM contacts c
INNER JOIN contact_tag_pivot ctp ON c.id = ctp.contact_id
INNER JOIN contact_tags ct ON ctp.contact_tag_id = ct.id
WHERE ct.name = 'VIP';

-- Contacts with ANY of specified tags (OR logic)
SELECT DISTINCT c.*
FROM contacts c
INNER JOIN contact_tag_pivot ctp ON c.id = ctp.contact_id
INNER JOIN contact_tags ct ON ctp.contact_tag_id = ct.id
WHERE ct.name IN ('VIP', 'Active', 'Newsletter');

-- Contacts with ALL specified tags (AND logic)
SELECT c.*
FROM contacts c
WHERE (
    SELECT COUNT(DISTINCT ct.name)
    FROM contact_tag_pivot ctp
    INNER JOIN contact_tags ct ON ctp.contact_tag_id = ct.id
    WHERE ctp.contact_id = c.id
    AND ct.name IN ('VIP', 'Active')
) = 2; -- Count matches number of required tags
```

#### Bulk Tag Operations
```php
// Tag multiple contacts
Contact::whereIn('id', [1, 2, 3, 4])
    ->each(function($contact) use ($tagId) {
        $contact->tags()->syncWithoutDetaching([$tagId]);
    });

// Remove tag from all contacts
$tag = ContactTag::find($tagId);
$tag->contacts()->detach();
```

---

## Events Published

The contacts-service publishes events to RabbitMQ for cross-service synchronization.

### Event Schema

All events follow standard message format:
```json
{
  "event_id": "uuid-v4",
  "event_type": "contact.created",
  "timestamp": "2025-10-03T14:30:00Z",
  "version": "1.0",
  "data": {
    "contact_id": 123,
    "email": "john.doe@example.com"
  },
  "metadata": {
    "correlation_id": "request-uuid",
    "service": "contacts-service"
  }
}
```

### 1. ContactCreated
**Queue:** contacts.created
**Published:** When new contact successfully created

**Payload:**
```json
{
  "event_type": "contact.created",
  "timestamp": "2025-10-03T14:30:00Z",
  "data": {
    "contact_id": 123,
    "email": "john.doe@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "company": "Acme Corp",
    "phone": "+33612345678",
    "country": "France",
    "city": "Paris",
    "created_at": "2025-10-03T14:30:00Z"
  }
}
```

**Consumers:**
- newsletters-service: Add to newsletter subscriber pool
- marketing-service: Sync to marketing automation platform
- analytics-service: Track new contact metrics

---

### 2. ContactUpdated
**Queue:** contacts.updated
**Published:** When contact information modified

**Payload:**
```json
{
  "event_type": "contact.updated",
  "timestamp": "2025-10-03T15:45:00Z",
  "data": {
    "contact_id": 123,
    "email": "john.doe@example.com",
    "updated_fields": {
      "company": "New Company Inc",
      "phone": "+33698765432",
      "city": "Lyon"
    },
    "updated_at": "2025-10-03T15:45:00Z"
  }
}
```

**Consumers:**
- newsletters-service: Update subscriber information
- marketing-service: Sync updated data
- analytics-service: Track contact changes

---

### 3. ContactAddedToList
**Queue:** contacts.list.added
**Published:** When contact added to list

**Payload:**
```json
{
  "event_type": "contact.added_to_list",
  "timestamp": "2025-10-03T16:00:00Z",
  "data": {
    "contact_id": 123,
    "list_id": 5,
    "list_name": "Newsletter Subscribers",
    "added_at": "2025-10-03T16:00:00Z"
  }
}
```

**Consumers:**
- newsletters-service: Subscribe to newsletter campaign
- marketing-service: Add to campaign audience
- analytics-service: Track list growth

---

### 4. ContactTagged
**Queue:** contacts.tagged
**Published:** When tag applied to contact

**Payload:**
```json
{
  "event_type": "contact.tagged",
  "timestamp": "2025-10-03T16:15:00Z",
  "data": {
    "contact_id": 123,
    "tag_id": 7,
    "tag_name": "VIP",
    "tagged_at": "2025-10-03T16:15:00Z"
  }
}
```

**Consumers:**
- newsletters-service: Apply VIP email templates
- marketing-service: Trigger VIP customer workflows
- analytics-service: Track segment changes

---

### 5. ContactDeleted
**Queue:** contacts.deleted
**Published:** When contact permanently deleted

**Payload:**
```json
{
  "event_type": "contact.deleted",
  "timestamp": "2025-10-03T17:00:00Z",
  "data": {
    "contact_id": 123,
    "email": "john.doe@example.com",
    "deleted_at": "2025-10-03T17:00:00Z",
    "reason": "user_request"
  }
}
```

**Consumers:**
- newsletters-service: Unsubscribe from all campaigns
- marketing-service: Remove from all audiences
- analytics-service: Archive contact metrics

**GDPR Compliance:**
```php
// Handle contact deletion with cascade cleanup
public function handleContactDeleted(array $event): void
{
    $contactId = $event['data']['contact_id'];

    // Remove from all lists
    DB::table('contact_list_contacts')
        ->where('contact_id', $contactId)
        ->delete();

    // Remove all tags
    DB::table('contact_tag_pivot')
        ->where('contact_id', $contactId)
        ->delete();

    // Anonymize in analytics (if needed)
    // Keep aggregated metrics, remove PII
}
```

---

## Cross-Service References

### Referenced BY Other Services

#### newsletters-service
```sql
-- subscribers table
CREATE TABLE subscribers (
    contact_id BIGINT UNSIGNED,  -- References contacts.id
    email VARCHAR(255),           -- Cache of contacts.email
    first_name VARCHAR(255),      -- Cache of contacts.first_name
    last_name VARCHAR(255),       -- Cache of contacts.last_name
    -- NO foreign key constraint (different database)
);
```

**Synchronization:**
- Listen to ContactCreated: Add to subscriber pool
- Listen to ContactUpdated: Update cached contact data
- Listen to ContactAddedToList: Subscribe to campaigns
- Listen to ContactDeleted: Unsubscribe and remove

---

#### marketing-service (future)
```sql
-- campaign_audiences table
CREATE TABLE campaign_audiences (
    contact_id BIGINT UNSIGNED,  -- References contacts.id
    email VARCHAR(255),           -- Cache of contacts.email
    -- NO foreign key constraint (different database)
);
```

**Synchronization:**
- Listen to ContactTagged: Add to targeted segments
- Listen to ContactAddedToList: Include in campaign audience

---

### References TO Other Services

#### auth-service
```sql
-- Local users table cached from auth-service
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,  -- From auth-service
    email VARCHAR(255) UNIQUE,       -- From auth-service
    -- Synchronized via RabbitMQ events
);
```

**Synchronization:**
- Listen to user.created: Add to local users cache
- Listen to user.updated: Update cached user data
- Listen to user.deleted: Remove from cache

---

## Indexes and Performance

### Strategic Indexes

#### contacts Table
```sql
PRIMARY KEY (id)                  -- Primary lookup
UNIQUE KEY (email)                -- Email-based queries
INDEX (first_name)                -- Name search
INDEX (last_name)                 -- Name search
INDEX (company)                   -- Company filtering
INDEX (country)                   -- Geographic segmentation
INDEX (city)                      -- Geographic segmentation
INDEX (created_at)                -- Time-based queries
```

**Query Optimization:**
```sql
-- Fast email lookup
SELECT * FROM contacts WHERE email = 'john.doe@example.com';

-- Geographic filtering
SELECT * FROM contacts
WHERE country = 'France' AND city = 'Paris';

-- Company contacts
SELECT * FROM contacts
WHERE company IS NOT NULL
ORDER BY company, last_name;

-- Recent contacts
SELECT * FROM contacts
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY created_at DESC;
```

---

#### contact_lists Table
```sql
PRIMARY KEY (id)
UNIQUE KEY (name)                 -- List name lookup
INDEX (is_active)                 -- Active list filtering
INDEX (created_at)                -- Sorting by creation date
```

**Query Optimization:**
```sql
-- Active lists only
SELECT * FROM contact_lists
WHERE is_active = 1
ORDER BY name;

-- Recent lists
SELECT * FROM contact_lists
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
ORDER BY created_at DESC;
```

---

#### contact_list_contacts Pivot
```sql
PRIMARY KEY (contact_list_id, contact_id)
INDEX (contact_id)                -- Reverse lookup
INDEX (added_at)                  -- Time-based sorting
```

**Query Optimization:**
```sql
-- Contacts in list with join date
SELECT c.*, clc.added_at
FROM contacts c
INNER JOIN contact_list_contacts clc ON c.id = clc.contact_id
WHERE clc.contact_list_id = 5
ORDER BY clc.added_at DESC;

-- Recently added to any list
SELECT c.*, cl.name as list_name, clc.added_at
FROM contacts c
INNER JOIN contact_list_contacts clc ON c.id = clc.contact_id
INNER JOIN contact_lists cl ON clc.contact_list_id = cl.id
WHERE clc.added_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY clc.added_at DESC;
```

---

#### contact_tags Table
```sql
PRIMARY KEY (id)
UNIQUE KEY (name)                 -- Tag name lookup
INDEX (created_at)                -- Sorting
```

---

### Performance Recommendations

1. **Contact Queries:**
   - Always use indexed columns in WHERE clauses
   - Use email for unique lookups (indexed)
   - Consider full-text search for name queries at scale

2. **List Operations:**
   - Cache active lists (rarely change)
   - Use batch operations for bulk add/remove
   - Consider pagination for large lists (10k+ contacts)

3. **Tag Queries:**
   - Cache tag-contact associations for frequent lookups
   - Use EXISTS for tag presence checks instead of JOIN
   - Optimize multi-tag queries with proper index usage

4. **Export Operations:**
   - Use queue jobs for large exports (>1k contacts)
   - Chunk queries to avoid memory issues
   - Stream CSV generation instead of loading all data

5. **Import Operations:**
   - Validate in chunks (500-1000 records per batch)
   - Use database transactions for atomicity
   - Queue processing for imports >5k records

---

## Import and Export Operations

### CSV Import Workflow

**Process:**
```
Upload CSV → Validation → Queue Job → Batch Processing → Confirmation
```

**Implementation:**
```php
// ContactImportJob.php
public function handle()
{
    $csvPath = $this->csvPath;
    $listId = $this->listId;

    // Read CSV in chunks
    $chunkSize = 500;
    $imported = 0;
    $errors = [];

    $csv = Reader::createFromPath($csvPath, 'r');
    $csv->setHeaderOffset(0);

    foreach ($csv->chunk($chunkSize) as $chunk) {
        DB::transaction(function() use ($chunk, $listId, &$imported, &$errors) {
            foreach ($chunk as $row) {
                try {
                    // Validate row
                    $validated = $this->validateRow($row);

                    // Create or update contact
                    $contact = Contact::updateOrCreate(
                        ['email' => $validated['email']],
                        $validated
                    );

                    // Add to list if specified
                    if ($listId) {
                        $contact->lists()->syncWithoutDetaching([
                            $listId => ['added_at' => now()]
                        ]);
                    }

                    $imported++;

                    // Publish event
                    event(new ContactCreated($contact));

                } catch (ValidationException $e) {
                    $errors[] = [
                        'row' => $row,
                        'error' => $e->getMessage()
                    ];
                }
            }
        });
    }

    return [
        'imported' => $imported,
        'errors' => $errors
    ];
}
```

**CSV Format:**
```csv
email,first_name,last_name,company,phone,country,city
john.doe@example.com,John,Doe,Acme Corp,+33612345678,France,Paris
jane.smith@example.com,Jane,Smith,Tech Inc,+33698765432,France,Lyon
```

**Validation Rules:**
- email: Required, valid format, unique
- first_name, last_name: Required
- company, phone, country, city: Optional
- Max 10,000 rows per import

---

### CSV Export Workflow

**Process:**
```
Export Request → Queue Job → Generate CSV → Store Temporarily → Download Link
```

**Implementation:**
```php
// ContactExportJob.php
public function handle()
{
    $listId = $this->listId;
    $filters = $this->filters;

    // Query contacts
    $query = Contact::query();

    if ($listId) {
        $query->whereHas('lists', function($q) use ($listId) {
            $q->where('contact_lists.id', $listId);
        });
    }

    // Apply additional filters
    if (!empty($filters['country'])) {
        $query->where('country', $filters['country']);
    }

    if (!empty($filters['tags'])) {
        $query->whereHas('tags', function($q) use ($filters) {
            $q->whereIn('name', $filters['tags']);
        });
    }

    // Generate CSV (streaming for memory efficiency)
    $filename = 'contacts_export_' . now()->format('Y-m-d_His') . '.csv';
    $filePath = storage_path('app/exports/' . $filename);

    $csv = Writer::createFromPath($filePath, 'w+');

    // Write header
    $csv->insertOne([
        'Email',
        'First Name',
        'Last Name',
        'Company',
        'Phone',
        'Country',
        'City',
        'Created At'
    ]);

    // Write data in chunks
    $query->chunk(1000, function($contacts) use ($csv) {
        foreach ($contacts as $contact) {
            $csv->insertOne([
                $contact->email,
                $contact->first_name,
                $contact->last_name,
                $contact->company,
                $contact->phone,
                $contact->country,
                $contact->city,
                $contact->created_at->toDateTimeString()
            ]);
        }
    });

    // Generate download link (expires in 24 hours)
    $downloadUrl = Storage::temporaryUrl($filePath, now()->addDay());

    return [
        'filename' => $filename,
        'download_url' => $downloadUrl,
        'expires_at' => now()->addDay()->toIso8601String()
    ];
}
```

---

### Bulk Operations

**Add Multiple Contacts to List:**
```php
// Efficient bulk insert
$contactIds = [1, 2, 3, 4, 5, /* ... up to 10k */];
$listId = 10;

$insertData = [];
$now = now();

foreach ($contactIds as $contactId) {
    $insertData[] = [
        'contact_list_id' => $listId,
        'contact_id' => $contactId,
        'added_at' => $now
    ];
}

// Batch insert (500 at a time to avoid query size limits)
collect($insertData)->chunk(500)->each(function($chunk) {
    DB::table('contact_list_contacts')->insertOrIgnore($chunk->toArray());
});
```

**Apply Tag to Multiple Contacts:**
```php
// Bulk tag application
$contactIds = [1, 2, 3, 4, 5];
$tagId = 7;

Contact::whereIn('id', $contactIds)
    ->each(function($contact) use ($tagId) {
        $contact->tags()->syncWithoutDetaching([$tagId]);
    });
```

---

## RBAC Integration

### Permission-Based Access Control

The contacts-service uses Spatie Laravel Permission for role-based access control.

#### Permission Checks in Controllers

```php
// ContactController.php
class ContactController extends Controller
{
    public function index()
    {
        $this->authorize('contacts.view');

        return Contact::paginate(50);
    }

    public function store(ContactRequest $request)
    {
        $this->authorize('contacts.create');

        $contact = Contact::create($request->validated());

        event(new ContactCreated($contact));

        return response()->json($contact, 201);
    }

    public function update(ContactRequest $request, Contact $contact)
    {
        $this->authorize('contacts.update');

        $contact->update($request->validated());

        event(new ContactUpdated($contact));

        return response()->json($contact);
    }

    public function destroy(Contact $contact)
    {
        $this->authorize('contacts.delete');

        $contact->delete();

        event(new ContactDeleted($contact));

        return response()->json(null, 204);
    }
}
```

#### List Management Permissions

```php
// ContactListController.php
public function store(ContactListRequest $request)
{
    $this->authorize('lists.manage');

    $list = ContactList::create($request->validated());

    return response()->json($list, 201);
}

public function addContacts(Request $request, ContactList $list)
{
    $this->authorize('lists.manage');

    $contactIds = $request->input('contact_ids');

    $list->contacts()->syncWithoutDetaching(
        collect($contactIds)->mapWithKeys(function($id) {
            return [$id => ['added_at' => now()]];
        })->toArray()
    );

    foreach ($contactIds as $contactId) {
        event(new ContactAddedToList($contactId, $list->id));
    }

    return response()->json(['message' => 'Contacts added to list']);
}
```

#### Tag Management Permissions

```php
// ContactTagController.php
public function store(Request $request)
{
    $this->authorize('tags.manage');

    $tag = ContactTag::create($request->validated());

    return response()->json($tag, 201);
}

public function applyToContact(Request $request, Contact $contact)
{
    $this->authorize('tags.manage');

    $tagIds = $request->input('tag_ids');

    $contact->tags()->syncWithoutDetaching($tagIds);

    foreach ($tagIds as $tagId) {
        event(new ContactTagged($contact->id, $tagId));
    }

    return response()->json(['message' => 'Tags applied to contact']);
}
```

#### Export/Import Permissions

```php
// ContactExportController.php
public function export(Request $request)
{
    $this->authorize('contacts.export');

    $filters = $request->validated();

    // Queue export job
    $job = new ContactExportJob($filters);
    dispatch($job);

    return response()->json([
        'message' => 'Export queued. You will receive download link when ready.'
    ]);
}

// ContactImportController.php
public function import(Request $request)
{
    $this->authorize('contacts.import');

    $file = $request->file('csv');
    $listId = $request->input('list_id');

    // Queue import job
    $job = new ContactImportJob($file->path(), $listId);
    dispatch($job);

    return response()->json([
        'message' => 'Import queued. Processing started.'
    ]);
}
```

---

## Backup and Maintenance

### Backup Strategy

#### Daily Automated Backup
```bash
#!/bin/bash
# scripts/backup-contacts-db.sh

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/contacts-service"
BACKUP_FILE="${BACKUP_DIR}/contacts_service_db_${TIMESTAMP}.sql"

mkdir -p ${BACKUP_DIR}

docker exec contacts-mysql mysqldump \
  --user=contacts_user \
  --password=contacts_password \
  --single-transaction \
  --routines \
  --triggers \
  --databases contacts_service_db \
  > ${BACKUP_FILE}

gzip ${BACKUP_FILE}

# Retention: Keep 30 days
find ${BACKUP_DIR} -name "*.sql.gz" -mtime +30 -delete

echo "Backup completed: ${BACKUP_FILE}.gz"
```

#### Backup Schedule (cron)
```cron
# Daily backup at 2:30 AM
30 2 * * * /path/to/scripts/backup-contacts-db.sh

# Weekly full backup (Sunday 3:30 AM)
30 3 * * 0 /path/to/scripts/full-backup-contacts.sh
```

---

### Database Maintenance

#### Optimize Tables (Monthly)
```sql
-- Defragment and update statistics
OPTIMIZE TABLE contacts;
OPTIMIZE TABLE contact_lists;
OPTIMIZE TABLE contact_tags;
OPTIMIZE TABLE contact_list_contacts;
OPTIMIZE TABLE contact_tag_pivot;

-- Analyze query patterns
ANALYZE TABLE contacts;
ANALYZE TABLE contact_lists;
ANALYZE TABLE contact_list_contacts;
```

#### Clean Expired Data
```sql
-- Remove contacts with invalid emails (optional, manual review)
-- Identify potential invalid emails
SELECT * FROM contacts
WHERE email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
LIMIT 100;

-- Archive inactive lists (inactive for 1+ year)
UPDATE contact_lists
SET is_active = 0
WHERE is_active = 1
AND updated_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Clean cache table
DELETE FROM cache
WHERE expiration < UNIX_TIMESTAMP(NOW());
```

---

### Monitoring Queries

#### Contact Growth
```sql
-- Daily new contacts (last 30 days)
SELECT
    DATE(created_at) AS date,
    COUNT(*) AS new_contacts,
    SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) AS total_contacts
FROM contacts
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

#### List Statistics
```sql
-- List sizes and activity
SELECT
    cl.id,
    cl.name,
    cl.is_active,
    COUNT(clc.contact_id) AS contact_count,
    MAX(clc.added_at) AS last_contact_added,
    cl.updated_at AS last_updated
FROM contact_lists cl
LEFT JOIN contact_list_contacts clc ON cl.id = clc.contact_list_id
GROUP BY cl.id, cl.name, cl.is_active, cl.updated_at
ORDER BY contact_count DESC;
```

#### Tag Usage
```sql
-- Most popular tags
SELECT
    ct.id,
    ct.name,
    ct.color,
    COUNT(ctp.contact_id) AS contact_count
FROM contact_tags ct
LEFT JOIN contact_tag_pivot ctp ON ct.id = ctp.contact_tag_id
GROUP BY ct.id, ct.name, ct.color
ORDER BY contact_count DESC
LIMIT 20;
```

#### Geographic Distribution
```sql
-- Contact distribution by country
SELECT
    country,
    COUNT(*) AS contact_count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM contacts), 2) AS percentage
FROM contacts
WHERE country IS NOT NULL
GROUP BY country
ORDER BY contact_count DESC
LIMIT 20;
```

---

### Health Checks

```php
// HealthCheckController.php
public function check(): JsonResponse
{
    $checks = [
        'database' => $this->checkDatabase(),
        'cache' => $this->checkCache(),
        'queue' => $this->checkQueue(),
        'contacts_count' => $this->getContactsCount(),
        'lists_count' => $this->getListsCount()
    ];

    $healthy = collect($checks)
        ->except(['contacts_count', 'lists_count'])
        ->every(fn($status) => $status === 'ok');

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String()
    ], $healthy ? 200 : 503);
}

private function getContactsCount(): int
{
    return Contact::count();
}

private function getListsCount(): int
{
    return ContactList::where('is_active', true)->count();
}
```

**Health Check Endpoint:**
```
GET /api/health
```

**Response:**
```json
{
  "status": "healthy",
  "checks": {
    "database": "ok",
    "cache": "ok",
    "queue": "ok",
    "contacts_count": 15234,
    "lists_count": 42
  },
  "timestamp": "2025-10-03T16:00:00Z"
}
```

---

## Related Documentation

- [Global Database Architecture](../00-global-database-architecture.md)
- [Database Relationships](../01-database-relationships.md)
- [RabbitMQ Message Broker Guide](../../architecture/rabbitmq-architecture.md)
- [Authentication Guide](../../development/jwt-authentication.md)
- [API Documentation](../../api/README.md)

---

**Document Version:** 1.0
**Last Updated:** 2025-10-03
**Database Version:** MySQL 8.0
**Laravel Version:** 12.x
