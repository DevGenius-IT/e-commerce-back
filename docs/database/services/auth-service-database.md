# Auth Service Database Documentation

## Table of Contents
- [Overview](#overview)
- [Database Configuration](#database-configuration)
- [Entity Relationship Diagram](#entity-relationship-diagram)
- [Table Specifications](#table-specifications)
- [Authentication and Authorization](#authentication-and-authorization)
- [RabbitMQ Events Published](#rabbitmq-events-published)
- [Cross-Service Relationships](#cross-service-relationships)
- [Indexes and Performance](#indexes-and-performance)
- [Security Considerations](#security-considerations)
- [Backup and Maintenance](#backup-and-maintenance)

## Overview

The auth-service is the centralized authentication and authorization service for the e-commerce platform. It manages user accounts, JWT token generation and validation, and role-based access control (RBAC) using Spatie Laravel Permission.

**Service Details:**
- Database Name: `auth_service_db`
- External Port: 3331 (for debugging and database clients)
- Container Port: 3306
- Service Port: 8001
- Framework: Laravel 12 with PHP 8.3+
- Authentication: JWT via tymon/jwt-auth
- Authorization: Role-Based Permissions via Spatie Laravel Permission

**Primary Responsibilities:**
- User registration and authentication
- JWT token generation, validation, and refresh
- Role and permission management
- User session tracking
- Password reset functionality
- Cross-service user data synchronization via RabbitMQ

## Database Configuration

**Connection Details (from .env):**
```env
DB_CONNECTION=mysql
DB_HOST=auth-mysql
DB_PORT=3306
DB_DATABASE=auth_service_db
DB_USERNAME=auth_user
DB_PASSWORD=auth_password

# External access for debugging
EXTERNAL_PORT=3331
```

**Docker Service Configuration:**
```yaml
auth-mysql:
  image: mysql:8.0
  ports:
    - "3331:3306"
  environment:
    MYSQL_DATABASE: auth_service_db
    MYSQL_USER: auth_user
    MYSQL_PASSWORD: auth_password
    MYSQL_ROOT_PASSWORD: root_password
```

**Character Set and Collation:**
```sql
CHARACTER SET: utf8mb4
COLLATION: utf8mb4_unicode_ci
```

## Entity Relationship Diagram

```mermaid
erDiagram
    users ||--o{ model_has_permissions : "has direct permissions"
    users ||--o{ model_has_roles : "has roles"
    users ||--o{ sessions : "has sessions"
    users ||--o| password_reset_tokens : "can request reset"

    roles ||--o{ model_has_roles : "assigned to users"
    roles ||--o{ role_has_permissions : "grants permissions"

    permissions ||--o{ model_has_permissions : "directly assigned"
    permissions ||--o{ role_has_permissions : "granted by role"

    users {
        bigint id PK
        string lastname
        string firstname
        string email UK
        timestamp email_verified_at
        string password
        string remember_token
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    roles {
        bigint id PK
        string name UK
        string guard_name
        timestamp created_at
        timestamp updated_at
    }

    permissions {
        bigint id PK
        string name UK
        string guard_name
        timestamp created_at
        timestamp updated_at
    }

    model_has_permissions {
        bigint permission_id PK_FK
        string model_type PK
        bigint model_id PK
    }

    model_has_roles {
        bigint role_id PK_FK
        string model_type PK
        bigint model_id PK
    }

    role_has_permissions {
        bigint permission_id PK_FK
        bigint role_id PK_FK
    }

    sessions {
        string id PK
        bigint user_id FK
        string ip_address
        text user_agent
        longtext payload
        int last_activity
    }

    password_reset_tokens {
        string email PK
        string token
        timestamp created_at
    }

    jobs {
        bigint id PK
        string queue
        longtext payload
        tinyint attempts
        int reserved_at
        int available_at
        int created_at
    }

    job_batches {
        string id PK
        string name
        int total_jobs
        int pending_jobs
        int failed_jobs
        longtext failed_job_ids
        mediumtext options
        int cancelled_at
        int created_at
        int finished_at
    }

    failed_jobs {
        bigint id PK
        string uuid UK
        text connection
        text queue
        longtext payload
        longtext exception
        timestamp failed_at
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

## Table Specifications

### Core Tables

#### users
Primary user authentication and profile table.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique user identifier |
| lastname | VARCHAR(255) | NOT NULL | User's last name |
| firstname | VARCHAR(255) | NOT NULL | User's first name |
| email | VARCHAR(255) | NOT NULL, UNIQUE | User's email address (login credential) |
| email_verified_at | TIMESTAMP | NULLABLE | Email verification timestamp |
| password | VARCHAR(255) | NOT NULL | Hashed password using bcrypt |
| remember_token | VARCHAR(100) | NULLABLE | Token for "remember me" functionality |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Record last update timestamp |
| deleted_at | TIMESTAMP | NULLABLE | Soft delete timestamp |

**Indexes:**
```sql
PRIMARY KEY (id)
UNIQUE KEY users_email_unique (email)
INDEX users_deleted_at_index (deleted_at)
```

**Business Rules:**
- Email must be unique across all users (active and soft-deleted)
- Password stored as bcrypt hash with cost factor 12
- Soft delete preserves data for audit trail while preventing login
- Email verification required before certain operations
- Firstname and lastname stored separately for flexible display options

**Model Features:**
```php
// Shared model: shared/Models/User.php
use HasFactory, Notifiable, SoftDeletes, HasRoles;

// JWT implementation
implements JWTSubject, MustVerifyEmail

// Mass assignable fields
$fillable = ['lastname', 'firstname', 'email', 'password'];

// Hidden from serialization
$hidden = ['password', 'remember_token'];

// Automatic casting
$casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed'
];
```

**Sample Data:**
```json
{
  "id": 1,
  "lastname": "Doe",
  "firstname": "John",
  "email": "john.doe@example.com",
  "email_verified_at": "2025-10-03T10:30:00Z",
  "password": "$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5n7DUr6V5tW1e",
  "remember_token": null,
  "created_at": "2025-10-03T10:00:00Z",
  "updated_at": "2025-10-03T10:30:00Z",
  "deleted_at": null
}
```

---

#### password_reset_tokens
Stores password reset tokens for secure password recovery.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| email | VARCHAR(255) | PRIMARY KEY | User's email address |
| token | VARCHAR(255) | NOT NULL | Hashed reset token |
| created_at | TIMESTAMP | NULLABLE | Token creation timestamp |

**Indexes:**
```sql
PRIMARY KEY (email)
```

**Business Rules:**
- One active reset token per email at a time
- Tokens expire after configurable period (default 60 minutes)
- Token stored as hash for security
- Old token automatically replaced on new reset request
- Token single-use only (deleted after successful password reset)

**Token Generation:**
```php
// Generate secure random token
$token = Str::random(64);

// Store hashed version
PasswordResetToken::create([
    'email' => $email,
    'token' => Hash::make($token),
    'created_at' => now()
]);

// Send unhashed token via email
// User submits token for verification
```

---

#### sessions
Tracks active user sessions for authentication state management.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | VARCHAR(255) | PRIMARY KEY | Session identifier (UUID) |
| user_id | BIGINT UNSIGNED | NULLABLE, INDEXED, FK | Associated user ID |
| ip_address | VARCHAR(45) | NULLABLE | Client IP address (IPv4/IPv6) |
| user_agent | TEXT | NULLABLE | Client browser/application identifier |
| payload | LONGTEXT | NOT NULL | Serialized session data |
| last_activity | INT | NOT NULL, INDEXED | Unix timestamp of last activity |

**Indexes:**
```sql
PRIMARY KEY (id)
INDEX sessions_user_id_index (user_id)
INDEX sessions_last_activity_index (last_activity)
```

**Business Rules:**
- Session lifetime configurable (default 120 minutes)
- Inactive sessions automatically garbage collected
- Supports both authenticated and guest sessions
- IP and user agent stored for security auditing
- Multiple concurrent sessions allowed per user

**Foreign Key (logical, not enforced):**
```sql
user_id -> users(id)
```

---

### Spatie Permission Tables

#### permissions
Defines granular permissions for system operations.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Permission identifier |
| name | VARCHAR(255) | NOT NULL | Permission name (e.g., "users.create") |
| guard_name | VARCHAR(255) | NOT NULL | Authentication guard name (default "api") |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Record last update timestamp |

**Indexes:**
```sql
PRIMARY KEY (id)
UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)
```

**Business Rules:**
- Permission names follow convention: "resource.action" (e.g., "orders.view", "products.create")
- Unique per guard to support multiple authentication systems
- Guard name typically "api" for JWT authentication
- Permissions can be assigned directly to users or via roles
- Hierarchical permissions handled at application level

**Common Permissions:**
```sql
INSERT INTO permissions (name, guard_name) VALUES
('users.view', 'api'),
('users.create', 'api'),
('users.update', 'api'),
('users.delete', 'api'),
('orders.view', 'api'),
('orders.manage', 'api'),
('products.view', 'api'),
('products.manage', 'api'),
('tickets.view', 'api'),
('tickets.update', 'api');
```

---

#### roles
Defines user roles that bundle permissions together.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Role identifier |
| name | VARCHAR(255) | NOT NULL | Role name (e.g., "admin", "customer") |
| guard_name | VARCHAR(255) | NOT NULL | Authentication guard name |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Record last update timestamp |

**Indexes:**
```sql
PRIMARY KEY (id)
UNIQUE KEY roles_name_guard_name_unique (name, guard_name)
```

**Business Rules:**
- Role names should be lowercase and descriptive
- Unique per guard
- Roles are collections of permissions for easier management
- Users can have multiple roles (permissions additive)
- Role changes affect all assigned users immediately

**Standard Roles:**
```sql
INSERT INTO roles (name, guard_name) VALUES
('super_admin', 'api'),        -- Full system access
('admin', 'api'),               -- Administrative access
('customer_service_agent', 'api'), -- Support ticket management
('warehouse_manager', 'api'),   -- Inventory and order fulfillment
('customer', 'api');            -- Standard customer access
```

---

#### model_has_permissions
Polymorphic pivot table for direct permission assignments to models.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| permission_id | BIGINT UNSIGNED | PRIMARY KEY, FK | Permission identifier |
| model_type | VARCHAR(255) | PRIMARY KEY | Model class name (e.g., "App\\Models\\User") |
| model_id | BIGINT UNSIGNED | PRIMARY KEY, INDEXED | Model instance identifier |

**Indexes:**
```sql
PRIMARY KEY (permission_id, model_id, model_type)
INDEX model_has_permissions_model_id_model_type_index (model_id, model_type)
FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
```

**Business Rules:**
- Allows direct permission assignment bypassing roles
- Polymorphic design supports assigning permissions to any model
- Primary use case: User model receiving direct permissions
- Composite primary key ensures unique permission per model instance
- Cascade delete removes assignments when permission deleted

**Usage Example:**
```php
// Give user direct permission
$user->givePermissionTo('products.manage');

// Check if user has permission (checks both direct and role permissions)
$user->hasPermissionTo('products.manage');
```

---

#### model_has_roles
Polymorphic pivot table for role assignments to models.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| role_id | BIGINT UNSIGNED | PRIMARY KEY, FK | Role identifier |
| model_type | VARCHAR(255) | PRIMARY KEY | Model class name |
| model_id | BIGINT UNSIGNED | PRIMARY KEY, INDEXED | Model instance identifier |

**Indexes:**
```sql
PRIMARY KEY (role_id, model_id, model_type)
INDEX model_has_roles_model_id_model_type_index (model_id, model_type)
FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
```

**Business Rules:**
- Users can have multiple roles simultaneously
- Permissions from all roles are combined (union)
- Polymorphic design for flexibility
- Cascade delete removes role assignments when role deleted

**Usage Example:**
```php
// Assign role to user
$user->assignRole('customer_service_agent');

// Assign multiple roles
$user->assignRole(['admin', 'warehouse_manager']);

// Check if user has role
$user->hasRole('admin');

// Get all user permissions (from all roles + direct)
$permissions = $user->getAllPermissions();
```

---

#### role_has_permissions
Pivot table defining permissions granted by each role.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| permission_id | BIGINT UNSIGNED | PRIMARY KEY, FK | Permission identifier |
| role_id | BIGINT UNSIGNED | PRIMARY KEY, FK | Role identifier |

**Indexes:**
```sql
PRIMARY KEY (permission_id, role_id)
FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
```

**Business Rules:**
- Defines permission bundles for roles
- Many-to-many relationship
- Changes to role permissions affect all users with that role
- Cascade delete maintains referential integrity

**Configuration Example:**
```php
// Define role permissions
$adminRole = Role::findByName('admin');
$adminRole->givePermissionTo([
    'users.view',
    'users.create',
    'users.update',
    'users.delete',
    'orders.view',
    'orders.manage',
    'products.view',
    'products.manage'
]);

$customerRole = Role::findByName('customer');
$customerRole->givePermissionTo([
    'products.view',
    'orders.view'
]);
```

---

### Queue and Cache Tables

#### jobs
Stores queued jobs for asynchronous processing.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Job identifier |
| queue | VARCHAR(255) | NOT NULL, INDEXED | Queue name |
| payload | LONGTEXT | NOT NULL | Serialized job data |
| attempts | TINYINT UNSIGNED | NOT NULL | Number of execution attempts |
| reserved_at | INT UNSIGNED | NULLABLE | Unix timestamp when job reserved |
| available_at | INT UNSIGNED | NOT NULL | Unix timestamp when job available |
| created_at | INT UNSIGNED | NOT NULL | Unix timestamp of creation |

**Indexes:**
```sql
PRIMARY KEY (id)
INDEX jobs_queue_index (queue)
```

**Business Rules:**
- Jobs processed by Laravel queue workers
- Failed jobs move to failed_jobs table after max attempts
- Queue name allows multiple queue prioritization
- Reserved jobs locked from duplicate processing

---

#### job_batches
Tracks batches of related jobs.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | VARCHAR(255) | PRIMARY KEY | Batch identifier (UUID) |
| name | VARCHAR(255) | NOT NULL | Human-readable batch name |
| total_jobs | INT | NOT NULL | Total jobs in batch |
| pending_jobs | INT | NOT NULL | Jobs not yet completed |
| failed_jobs | INT | NOT NULL | Jobs that failed |
| failed_job_ids | LONGTEXT | NOT NULL | JSON array of failed job IDs |
| options | MEDIUMTEXT | NULLABLE | JSON batch options |
| cancelled_at | INT | NULLABLE | Unix timestamp of cancellation |
| created_at | INT | NOT NULL | Unix timestamp of creation |
| finished_at | INT | NULLABLE | Unix timestamp of completion |

**Indexes:**
```sql
PRIMARY KEY (id)
```

**Business Rules:**
- Allows coordinating multiple related jobs
- Progress tracking (pending/failed counts)
- Batch can be cancelled mid-execution
- Useful for bulk operations (e.g., sending 1000 emails)

---

#### failed_jobs
Stores jobs that exceeded max retry attempts.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Failed job identifier |
| uuid | VARCHAR(255) | NOT NULL, UNIQUE | Job UUID for idempotency |
| connection | TEXT | NOT NULL | Queue connection name |
| queue | TEXT | NOT NULL | Queue name |
| payload | LONGTEXT | NOT NULL | Original job payload |
| exception | LONGTEXT | NOT NULL | Exception message and stack trace |
| failed_at | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Failure timestamp |

**Indexes:**
```sql
PRIMARY KEY (id)
UNIQUE KEY failed_jobs_uuid_unique (uuid)
```

**Business Rules:**
- Manual intervention required to reprocess
- Exception details aid debugging
- UUID prevents duplicate entries
- Retention policy: 30 days (configurable)

---

#### cache
Key-value store for application caching.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| key | VARCHAR(255) | PRIMARY KEY | Cache key |
| value | MEDIUMTEXT | NOT NULL | Serialized cached value |
| expiration | INT | NOT NULL | Unix timestamp of expiration |

**Indexes:**
```sql
PRIMARY KEY (key)
```

**Business Rules:**
- Expired entries automatically deleted by Laravel
- Supports any serializable PHP value
- Used for JWT token blacklist, rate limiting, session data
- Alternative to Redis for smaller deployments

---

#### cache_locks
Distributed lock mechanism for cache operations.

**Columns:**
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| key | VARCHAR(255) | PRIMARY KEY | Lock key |
| owner | VARCHAR(255) | NOT NULL | Lock owner identifier |
| expiration | INT | NOT NULL | Unix timestamp of lock expiration |

**Indexes:**
```sql
PRIMARY KEY (key)
```

**Business Rules:**
- Prevents race conditions in concurrent operations
- Automatic expiration prevents deadlocks
- Owner identifier allows lock verification

---

## Authentication and Authorization

### JWT Token Flow

#### Token Generation
```php
// AuthService.php - register() and login() methods
public function register(array $data): array
{
    $user = User::create([
        'lastname' => $data['lastname'],
        'firstname' => $data['firstname'],
        'email' => $data['email'],
        'password' => Hash::make($data['password'])
    ]);

    $token = JWTAuth::fromUser($user);

    return [
        'user' => $user,
        'token' => $token,
        'token_type' => 'bearer',
        'expires_in' => config('jwt.ttl') * 60  // seconds
    ];
}
```

**Token Payload:**
```json
{
  "iss": "http://auth-service",
  "sub": 123,
  "iat": 1696329000,
  "exp": 1696332600,
  "nbf": 1696329000,
  "jti": "a1b2c3d4e5f6",
  "role": "customer",
  "email": "john.doe@example.com"
}
```

**Configuration (config/jwt.php):**
```php
'ttl' => env('JWT_TTL', 60),  // Token lifetime in minutes
'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),  // 14 days
'algo' => env('JWT_ALGO', 'HS256'),
'secret' => env('JWT_SECRET'),
```

#### Token Validation
```php
// AuthService.php - validateToken() method
public function validateToken(string $token): array
{
    try {
        $user = JWTAuth::setToken($token)->authenticate();
        return [
            'valid' => true,
            'user' => $user
        ];
    } catch (TokenExpiredException $e) {
        return [
            'valid' => false,
            'message' => 'Token expired'
        ];
    } catch (TokenInvalidException $e) {
        return [
            'valid' => false,
            'message' => 'Invalid token'
        ];
    } catch (JWTException $e) {
        return [
            'valid' => false,
            'message' => 'Token validation failed'
        ];
    }
}
```

#### Token Refresh
```php
// AuthService.php - refreshToken() method
public function refreshToken(): array
{
    try {
        $token = JWTAuth::parseToken()->refresh();
        return [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60
        ];
    } catch (TokenExpiredException $e) {
        throw new AuthenticationException('Refresh token expired');
    }
}
```

**Refresh Flow:**
1. Client sends expired token to /refresh endpoint
2. Service validates token signature (ignoring expiration)
3. Checks if token within refresh TTL window
4. Generates new token with extended expiration
5. Old token added to blacklist (cache)

---

### Role-Based Access Control (RBAC)

#### Permission Checking
```php
// Middleware: CheckPermission.php
public function handle(Request $request, Closure $next, string $permission)
{
    $user = auth()->user();

    if (!$user || !$user->hasPermissionTo($permission)) {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => "Permission required: {$permission}"
        ], 403);
    }

    return $next($request);
}

// Route usage
Route::middleware(['auth:api', 'permission:users.create'])
    ->post('/users', [UserController::class, 'store']);
```

#### Role Checking
```php
// Middleware: CheckRole.php
public function handle(Request $request, Closure $next, string $role)
{
    $user = auth()->user();

    if (!$user || !$user->hasRole($role)) {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => "Role required: {$role}"
        ], 403);
    }

    return $next($request);
}

// Route usage
Route::middleware(['auth:api', 'role:admin'])
    ->get('/admin/dashboard', [AdminController::class, 'dashboard']);
```

#### Permission Hierarchy Query
```sql
-- Get all permissions for a user (direct + via roles)
SELECT DISTINCT p.name
FROM permissions p
LEFT JOIN model_has_permissions mhp
    ON p.id = mhp.permission_id
    AND mhp.model_type = 'App\\Models\\User'
    AND mhp.model_id = 123
LEFT JOIN role_has_permissions rhp ON p.id = rhp.permission_id
LEFT JOIN model_has_roles mhr
    ON rhp.role_id = mhr.role_id
    AND mhr.model_type = 'App\\Models\\User'
    AND mhr.model_id = 123
WHERE mhp.permission_id IS NOT NULL
   OR rhp.permission_id IS NOT NULL;
```

---

### API Endpoints

#### Authentication Endpoints
```
POST   /api/auth/register      - Register new user
POST   /api/auth/login         - Login and get JWT token
POST   /api/auth/logout        - Invalidate JWT token
POST   /api/auth/refresh       - Refresh JWT token
GET    /api/auth/me            - Get authenticated user
POST   /api/auth/validate      - Validate JWT token
```

#### Role Management Endpoints
```
GET    /api/roles              - List all roles
POST   /api/roles              - Create new role
GET    /api/roles/{id}         - Get role details
PUT    /api/roles/{id}         - Update role
DELETE /api/roles/{id}         - Delete role
POST   /api/roles/{id}/permissions - Assign permissions to role
```

#### Permission Management Endpoints
```
GET    /api/permissions        - List all permissions
POST   /api/permissions        - Create new permission
GET    /api/permissions/{id}   - Get permission details
PUT    /api/permissions/{id}   - Update permission
DELETE /api/permissions/{id}   - Delete permission
```

---

## RabbitMQ Events Published

The auth-service publishes events to RabbitMQ for cross-service synchronization.

### Exchange Configuration
```yaml
exchange: microservices_exchange
type: topic
durable: true
```

### Published Events

#### 1. user.created
Published when a new user registers.

**Routing Key:** `auth.user.created`

**Payload:**
```json
{
  "event_id": "550e8400-e29b-41d4-a716-446655440000",
  "event_type": "user.created",
  "timestamp": "2025-10-03T10:30:00Z",
  "version": "1.0",
  "data": {
    "user_id": 123,
    "email": "john.doe@example.com",
    "firstname": "John",
    "lastname": "Doe",
    "created_at": "2025-10-03T10:30:00Z"
  },
  "metadata": {
    "correlation_id": "req-uuid-v4",
    "causation_id": null,
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"
  }
}
```

**Subscribers:**
- baskets-service: Create empty basket for new user
- addresses-service: Initialize address book
- orders-service: Enable order placement
- sav-service: Enable support ticket creation
- contacts-service: Add to contact management
- newsletters-service: Add to mailing list
- websites-service: Initialize user preferences

**Publishing Code:**
```php
// After user creation in AuthService::register()
event(new UserCreated($user));

// Event class publishes to RabbitMQ
class UserCreated implements ShouldBroadcast
{
    public function __construct(public User $user) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('microservices_exchange')
        ];
    }

    public function broadcastAs(): string
    {
        return 'auth.user.created';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => Str::uuid(),
            'event_type' => 'user.created',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0',
            'data' => [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'firstname' => $this->user->firstname,
                'lastname' => $this->user->lastname,
                'created_at' => $this->user->created_at->toIso8601String()
            ]
        ];
    }
}
```

---

#### 2. user.updated
Published when user profile or credentials are updated.

**Routing Key:** `auth.user.updated`

**Payload:**
```json
{
  "event_id": "660e9500-f39c-52e5-b827-557766551111",
  "event_type": "user.updated",
  "timestamp": "2025-10-03T11:45:00Z",
  "version": "1.0",
  "data": {
    "user_id": 123,
    "updated_fields": {
      "firstname": "Jonathan",
      "lastname": "Smith",
      "email": "jonathan.smith@example.com"
    },
    "updated_at": "2025-10-03T11:45:00Z"
  },
  "metadata": {
    "correlation_id": "req-uuid-v4",
    "causation_id": "user.login-event-uuid"
  }
}
```

**Subscribers:**
- baskets-service: Update cached user data
- orders-service: Update user information in orders
- addresses-service: Sync contact information
- sav-service: Update support ticket user details
- contacts-service: Sync contact records
- newsletters-service: Update subscriber information

**Business Rules:**
- Only changed fields included in `updated_fields`
- Email change triggers email verification workflow
- Password changes invalidate all existing JWT tokens
- Services update only relevant cached data

---

#### 3. user.deleted
Published when user account is soft-deleted (GDPR compliance).

**Routing Key:** `auth.user.deleted`

**Payload:**
```json
{
  "event_id": "770e0600-g40d-63f6-c938-668877662222",
  "event_type": "user.deleted",
  "timestamp": "2025-10-03T14:00:00Z",
  "version": "1.0",
  "data": {
    "user_id": 123,
    "deleted_at": "2025-10-03T14:00:00Z",
    "soft_delete": true,
    "reason": "user_request"
  },
  "metadata": {
    "correlation_id": "req-uuid-v4",
    "causation_id": null
  }
}
```

**Subscribers:**
- baskets-service: Archive user baskets
- orders-service: Anonymize order records (keep for accounting)
- addresses-service: Delete user addresses
- sav-service: Anonymize support tickets (keep for records)
- contacts-service: Remove contact records
- newsletters-service: Unsubscribe and remove from lists

**GDPR Compliance:**
```php
// Anonymization in consuming services
public function handleUserDeleted(array $event): void
{
    $userId = $event['data']['user_id'];

    // Anonymize instead of hard delete
    Order::where('user_id', $userId)->update([
        'user_email' => 'deleted@example.com',
        'user_name' => 'Deleted User',
        'user_phone' => null
    ]);

    // Soft delete user cache
    User::where('id', $userId)->delete();
}
```

---

#### 4. role.assigned
Published when a role is assigned to a user.

**Routing Key:** `auth.role.assigned`

**Payload:**
```json
{
  "event_id": "880e1700-h51e-74g7-d049-779988773333",
  "event_type": "role.assigned",
  "timestamp": "2025-10-03T12:15:00Z",
  "version": "1.0",
  "data": {
    "user_id": 123,
    "role_id": 5,
    "role_name": "customer_service_agent",
    "permissions": [
      "tickets.view",
      "tickets.update",
      "tickets.comment",
      "users.view"
    ],
    "assigned_at": "2025-10-03T12:15:00Z"
  },
  "metadata": {
    "correlation_id": "req-uuid-v4",
    "assigned_by": 1
  }
}
```

**Subscribers:**
- sav-service: Add to agent assignment pool
- contacts-service: Grant contact management access
- api-gateway: Update permission cache

---

#### 5. role.revoked
Published when a role is removed from a user.

**Routing Key:** `auth.role.revoked`

**Payload:**
```json
{
  "event_id": "990e2800-i62f-85h8-e150-880099884444",
  "event_type": "role.revoked",
  "timestamp": "2025-10-03T13:30:00Z",
  "version": "1.0",
  "data": {
    "user_id": 123,
    "role_id": 5,
    "role_name": "customer_service_agent",
    "revoked_at": "2025-10-03T13:30:00Z"
  },
  "metadata": {
    "correlation_id": "req-uuid-v4",
    "revoked_by": 1
  }
}
```

**Subscribers:**
- sav-service: Remove from agent pool, reassign tickets
- contacts-service: Revoke contact access
- api-gateway: Update permission cache

---

#### 6. permission.granted
Published when a direct permission is granted to a user.

**Routing Key:** `auth.permission.granted`

**Payload:**
```json
{
  "event_id": "aa0e3900-j73g-96i9-f261-991100995555",
  "event_type": "permission.granted",
  "timestamp": "2025-10-03T15:00:00Z",
  "version": "1.0",
  "data": {
    "user_id": 123,
    "permission_id": 42,
    "permission_name": "products.manage",
    "granted_at": "2025-10-03T15:00:00Z"
  },
  "metadata": {
    "correlation_id": "req-uuid-v4",
    "granted_by": 1
  }
}
```

---

## Cross-Service Relationships

The auth-service user ID is referenced across multiple services for data ownership and authorization.

### Services Referencing user_id

#### 1. addresses-service
```sql
-- addresses_service_db.addresses
user_id BIGINT UNSIGNED NOT NULL
FOREIGN KEY (logical): user_id -> auth_service_db.users.id
```
**Relationship:** One user has many addresses (billing, shipping)

---

#### 2. baskets-service
```sql
-- baskets_service_db.baskets
user_id BIGINT UNSIGNED NOT NULL
FOREIGN KEY (logical): user_id -> auth_service_db.users.id
```
**Relationship:** One user has one active basket (shopping cart)

---

#### 3. orders-service
```sql
-- orders_service_db.orders
user_id BIGINT UNSIGNED NOT NULL
FOREIGN KEY (logical): user_id -> auth_service_db.users.id
```
**Relationship:** One user has many orders (purchase history)

---

#### 4. sav-service (Support Tickets)
```sql
-- sav_service_db.support_tickets
user_id BIGINT UNSIGNED NOT NULL
FOREIGN KEY (logical): user_id -> auth_service_db.users.id
```
**Relationship:** One user has many support tickets

---

#### 5. contacts-service
```sql
-- contacts_service_db.contacts
user_id BIGINT UNSIGNED NULLABLE
FOREIGN KEY (logical): user_id -> auth_service_db.users.id
```
**Relationship:** One user can have many contact form submissions (nullable for guest contacts)

---

#### 6. newsletters-service
```sql
-- newsletters_service_db.subscribers
user_id BIGINT UNSIGNED NULLABLE
FOREIGN KEY (logical): user_id -> auth_service_db.users.id
```
**Relationship:** One user can be a newsletter subscriber (nullable for non-registered subscribers)

---

#### 7. websites-service
```sql
-- websites_service_db.user_preferences
user_id BIGINT UNSIGNED NOT NULL
FOREIGN KEY (logical): user_id -> auth_service_db.users.id
```
**Relationship:** One user has one set of website preferences per website

---

### Cross-Service Data Synchronization

#### Local User Cache Pattern
Services maintain a local denormalized `users` table for performance:

```sql
-- Example: baskets_service_db.users (local cache)
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    firstname VARCHAR(255),
    lastname VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    INDEX (email),
    INDEX (deleted_at)
);
```

**Synchronization Flow:**
1. Auth-service publishes `user.created`, `user.updated`, `user.deleted` events
2. Consumer services subscribe to these events
3. Each service updates its local user cache
4. Services query local cache for user data (no cross-database queries)
5. Eventual consistency window typically < 1 second

**Consistency Guarantees:**
- Eventual consistency (not strong consistency)
- Idempotent event handlers prevent duplicate processing
- Event ordering preserved via RabbitMQ
- Conflict resolution: Last-write-wins based on timestamp

---

### Referential Integrity Handling

#### Hard Delete Protection
```php
// Before deleting user in auth-service
public function deleteUser(int $userId): void
{
    // Check for active relationships
    $hasActiveOrders = $this->checkViaRabbitMQ('orders.check', ['user_id' => $userId]);
    $hasOpenTickets = $this->checkViaRabbitMQ('sav.check', ['user_id' => $userId]);

    if ($hasActiveOrders || $hasOpenTickets) {
        throw new CannotDeleteUserException(
            'User has active orders or open support tickets. Use soft delete instead.'
        );
    }

    // Safe to hard delete
    User::find($userId)->forceDelete();
}
```

#### Soft Delete Cascade
```php
// User deletion triggers cascade in other services
public function handleUserDeleted(array $event): void
{
    $userId = $event['data']['user_id'];

    // Archive baskets
    Basket::where('user_id', $userId)->update(['archived_at' => now()]);

    // Anonymize orders (keep for accounting)
    Order::where('user_id', $userId)->update([
        'user_email' => 'deleted@example.com',
        'user_name' => 'Deleted User'
    ]);

    // Soft delete local user cache
    User::where('id', $userId)->delete();
}
```

---

## Indexes and Performance

### Primary Indexes

#### users table
```sql
PRIMARY KEY (id)                          -- Clustered index for primary key lookups
UNIQUE KEY users_email_unique (email)     -- Fast login by email
INDEX users_deleted_at_index (deleted_at) -- Soft delete filtering
```

**Query Optimization:**
```sql
-- Optimized login query (uses email unique index)
SELECT * FROM users
WHERE email = 'john.doe@example.com'
AND deleted_at IS NULL;

-- Optimized active users query (uses deleted_at index)
SELECT COUNT(*) FROM users WHERE deleted_at IS NULL;
```

---

#### sessions table
```sql
PRIMARY KEY (id)                              -- Session ID lookups
INDEX sessions_user_id_index (user_id)        -- User's sessions
INDEX sessions_last_activity_index (last_activity) -- Garbage collection
```

**Query Optimization:**
```sql
-- Get user's active sessions (uses user_id index)
SELECT * FROM sessions
WHERE user_id = 123
ORDER BY last_activity DESC;

-- Garbage collect expired sessions (uses last_activity index)
DELETE FROM sessions
WHERE last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 2 HOUR));
```

---

#### Spatie Permission Indexes
```sql
-- permissions table
UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)

-- roles table
UNIQUE KEY roles_name_guard_name_unique (name, guard_name)

-- model_has_permissions table
PRIMARY KEY (permission_id, model_id, model_type)
INDEX model_has_permissions_model_id_model_type_index (model_id, model_type)

-- model_has_roles table
PRIMARY KEY (role_id, model_id, model_type)
INDEX model_has_roles_model_id_model_type_index (model_id, model_type)

-- role_has_permissions table
PRIMARY KEY (permission_id, role_id)
```

**Permission Check Query Optimization:**
```sql
-- Optimized permission check (uses composite indexes)
SELECT EXISTS (
    SELECT 1 FROM model_has_permissions
    WHERE permission_id = 42
      AND model_type = 'App\\Models\\User'
      AND model_id = 123
    UNION
    SELECT 1 FROM role_has_permissions rhp
    INNER JOIN model_has_roles mhr
        ON rhp.role_id = mhr.role_id
    WHERE rhp.permission_id = 42
      AND mhr.model_type = 'App\\Models\\User'
      AND mhr.model_id = 123
) AS has_permission;
```

---

### Performance Tuning

#### Query Performance Targets
```yaml
user_lookup_by_id: < 5ms
user_lookup_by_email: < 10ms
permission_check: < 20ms
session_lookup: < 5ms
token_validation: < 50ms (includes JWT decode)
```

#### Database Connection Pooling
```env
# config/database.php connections
DB_CONNECTION=mysql
DB_MAX_CONNECTIONS=100
DB_IDLE_TIMEOUT=10000
DB_CONNECT_TIMEOUT=10
```

#### Query Caching Strategy
```php
// Cache permission lookups (5 minutes)
$permissions = Cache::remember(
    "user.{$userId}.permissions",
    300,
    fn() => $user->getAllPermissions()->pluck('name')->toArray()
);

// Cache role lookups (5 minutes)
$roles = Cache::remember(
    "user.{$userId}.roles",
    300,
    fn() => $user->roles->pluck('name')->toArray()
);

// Invalidate cache on permission changes
Event::listen(RoleAssigned::class, function ($event) {
    Cache::forget("user.{$event->userId}.permissions");
    Cache::forget("user.{$event->userId}.roles");
});
```

---

## Security Considerations

### Password Security

#### Hashing Configuration
```php
// config/hashing.php
'driver' => 'bcrypt',
'bcrypt' => [
    'rounds' => 12,  // Cost factor (higher = slower but more secure)
],

// Automatic hashing via model cast
protected function casts(): array
{
    return [
        'password' => 'hashed',
    ];
}
```

**Password Requirements:**
```php
// RegisterRequest.php validation rules
'password' => [
    'required',
    'string',
    'min:8',
    'confirmed',
    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
];

// Requirements:
// - Minimum 8 characters
// - At least one uppercase letter
// - At least one lowercase letter
// - At least one digit
// - At least one special character
```

---

### JWT Token Security

#### Token Storage
```yaml
client_storage:
  recommended: httpOnly cookies
  alternative: localStorage (CSRF protection required)
  avoid: sessionStorage (less secure)

token_transmission:
  header: "Authorization: Bearer {token}"
  cookie: httpOnly, secure, sameSite=strict
```

#### Token Blacklisting
```php
// Logout invalidates token
public function logout(): void
{
    $token = JWTAuth::getToken();
    $payload = JWTAuth::getPayload($token);
    $exp = $payload->get('exp');

    // Add to blacklist (cache) until expiration
    Cache::put(
        "jwt.blacklist.{$token}",
        true,
        Carbon::createFromTimestamp($exp)
    );

    JWTAuth::invalidate($token);
}

// Validate token not blacklisted
public function validateToken(string $token): bool
{
    if (Cache::has("jwt.blacklist.{$token}")) {
        return false;
    }

    return JWTAuth::setToken($token)->check();
}
```

---

### SQL Injection Prevention

All queries use Laravel's Eloquent ORM and query builder with parameter binding:

```php
// SAFE: Parameterized query
$user = User::where('email', $email)->first();

// SAFE: Eloquent binding
$users = User::whereIn('id', $userIds)->get();

// DANGEROUS: Raw query without binding (NEVER DO THIS)
$user = DB::select("SELECT * FROM users WHERE email = '{$email}'");

// SAFE: Raw query with bindings
$user = DB::select("SELECT * FROM users WHERE email = ?", [$email]);
```

---

### Rate Limiting

```php
// routes/api.php
Route::middleware(['throttle:login'])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// config/throttle.php
'login' => [
    'limit' => 5,        // 5 attempts
    'decay' => 300,      // per 5 minutes
    'response' => 'Too many login attempts. Please try again in 5 minutes.'
],

'api' => [
    'limit' => 60,       // 60 requests
    'decay' => 60,       // per minute
]
```

---

### CORS Configuration

```php
// config/cors.php
'paths' => ['api/*', 'auth/*'],
'allowed_origins' => [
    'https://example.com',
    'https://www.example.com'
],
'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
'allowed_headers' => ['Content-Type', 'Authorization'],
'exposed_headers' => ['Authorization'],
'max_age' => 3600,
'supports_credentials' => true,
```

---

### Audit Logging

```php
// Log all authentication events
Log::channel('auth')->info('User login', [
    'user_id' => $user->id,
    'email' => $user->email,
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'timestamp' => now()
]);

// Log permission changes
Log::channel('security')->info('Role assigned', [
    'user_id' => $user->id,
    'role_id' => $role->id,
    'role_name' => $role->name,
    'assigned_by' => auth()->id(),
    'timestamp' => now()
]);
```

---

## Backup and Maintenance

### Backup Strategy

#### Daily Automated Backup
```bash
# scripts/backup-database.sh
#!/bin/bash
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/auth-service"
BACKUP_FILE="${BACKUP_DIR}/auth_service_db_${TIMESTAMP}.sql"

mkdir -p ${BACKUP_DIR}

docker exec auth-mysql mysqldump \
  --user=auth_user \
  --password=auth_password \
  --single-transaction \
  --routines \
  --triggers \
  --databases auth_service_db \
  > ${BACKUP_FILE}

gzip ${BACKUP_FILE}

# Retention: Keep 30 days
find ${BACKUP_DIR} -name "*.sql.gz" -mtime +30 -delete

echo "Backup completed: ${BACKUP_FILE}.gz"
```

#### Backup Schedule (cron)
```cron
# Daily backup at 2 AM
0 2 * * * /path/to/scripts/backup-database.sh

# Weekly full backup (Sunday 3 AM)
0 3 * * 0 /path/to/scripts/full-backup.sh
```

---

### Database Maintenance

#### Optimize Tables (Monthly)
```sql
-- Defragment and update statistics
OPTIMIZE TABLE users;
OPTIMIZE TABLE sessions;
OPTIMIZE TABLE permissions;
OPTIMIZE TABLE roles;
OPTIMIZE TABLE model_has_permissions;
OPTIMIZE TABLE model_has_roles;
OPTIMIZE TABLE role_has_permissions;

-- Analyze query patterns
ANALYZE TABLE users;
ANALYZE TABLE sessions;
```

#### Clean Expired Data
```sql
-- Remove expired password reset tokens (older than 60 minutes)
DELETE FROM password_reset_tokens
WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 MINUTE);

-- Remove old sessions (older than 30 days)
DELETE FROM sessions
WHERE last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY));

-- Clean cache table (expired entries)
DELETE FROM cache
WHERE expiration < UNIX_TIMESTAMP(NOW());

-- Clean failed jobs (older than 30 days)
DELETE FROM failed_jobs
WHERE failed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

### Monitoring Queries

#### Active Sessions
```sql
SELECT
    u.id AS user_id,
    u.email,
    COUNT(s.id) AS session_count,
    MAX(FROM_UNIXTIME(s.last_activity)) AS last_activity
FROM users u
LEFT JOIN sessions s ON u.id = s.user_id
WHERE s.last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 HOUR))
GROUP BY u.id, u.email
ORDER BY session_count DESC
LIMIT 20;
```

#### User Growth
```sql
SELECT
    DATE(created_at) AS date,
    COUNT(*) AS new_users,
    SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) AS total_users
FROM users
WHERE deleted_at IS NULL
GROUP BY DATE(created_at)
ORDER BY date DESC
LIMIT 30;
```

#### Permission Usage
```sql
SELECT
    p.name AS permission,
    COUNT(DISTINCT mhp.model_id) AS direct_assignments,
    COUNT(DISTINCT mhr.model_id) AS role_assignments
FROM permissions p
LEFT JOIN model_has_permissions mhp ON p.id = mhp.permission_id
LEFT JOIN role_has_permissions rhp ON p.id = rhp.permission_id
LEFT JOIN model_has_roles mhr ON rhp.role_id = mhr.role_id
GROUP BY p.id, p.name
ORDER BY direct_assignments + role_assignments DESC;
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
        'rabbitmq' => $this->checkRabbitMQ(),
    ];

    $healthy = collect($checks)->every(fn($status) => $status === 'ok');

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String()
    ], $healthy ? 200 : 503);
}

private function checkDatabase(): string
{
    try {
        DB::connection()->getPdo();
        return 'ok';
    } catch (Exception $e) {
        return 'error: ' . $e->getMessage();
    }
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
    "rabbitmq": "ok"
  },
  "timestamp": "2025-10-03T16:00:00Z"
}
```

---

## Related Documentation

- [Global Database Architecture](../00-global-database-architecture.md)
- [Database Relationships](../01-database-relationships.md)
- [RabbitMQ Message Broker Guide](../../architecture/rabbitmq-architecture.md)
- [JWT Authentication Guide](../../development/jwt-authentication.md)
- [API Documentation](../../api/README.md)

---

**Document Version:** 1.0
**Last Updated:** 2025-10-03
**Maintainer:** Development Team
