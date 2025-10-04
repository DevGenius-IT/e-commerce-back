# Authentication Service

## Service Overview

**Purpose**: Centralized authentication and authorization service using JWT tokens and role-based access control (RBAC).

**Port**: 8000
**Database**: auth_db (MySQL 8.0)
**External Port**: 3307 (for debugging)
**Dependencies**: None (foundational service)

**Authentication Method**: JWT tokens via tymon/jwt-auth
**Authorization Framework**: Spatie Laravel Permission (RBAC)

## Responsibilities

- User registration and authentication
- JWT token generation, validation, and refresh
- Role-based access control (RBAC)
- Permission management
- User profile management
- Password hashing and security

## API Endpoints

| Method | Endpoint | Description | Auth Required | Request/Response |
|--------|----------|-------------|---------------|------------------|
| GET | /health | Service health check | No | {"status":"healthy","service":"auth-service"} |
| POST | /register | Register new user | No | {"email","password","name"} -> JWT token |
| POST | /login | Authenticate user | No | {"email","password"} -> JWT token |
| POST | /validate-token | Validate JWT token | No | {"token"} -> {"valid":true,"user":{}} |
| POST | /logout | Invalidate current token | Yes | Success message |
| POST | /refresh | Refresh JWT token | Yes | New JWT token |
| GET | /me | Get authenticated user | Yes | User object with roles/permissions |
| GET | /roles | List all roles | Yes | [{"id","name","permissions":[]}] |
| POST | /roles | Create new role | Yes | {"name","guard_name"} -> Role object |
| GET | /roles/{id} | Get role details | Yes | {"id","name","permissions":[]} |
| PUT | /roles/{id} | Update role | Yes | {"name"} -> Updated role |
| DELETE | /roles/{id} | Delete role | Yes | Success message |
| POST | /roles/{id}/permissions | Assign permissions to role | Yes | {"permissions":[]} -> Updated role |
| GET | /permissions | List all permissions | Yes | [{"id","name","guard_name"}] |
| POST | /permissions | Create permission | Yes | {"name","guard_name"} -> Permission |
| GET | /permissions/{id} | Get permission details | Yes | Permission object |
| PUT | /permissions/{id} | Update permission | Yes | Updated permission |
| DELETE | /permissions/{id} | Delete permission | Yes | Success message |
| POST | /users/{id}/roles | Assign role to user | Yes | {"role"} -> Updated user |
| DELETE | /users/{id}/roles/{role} | Remove role from user | Yes | Success message |
| POST | /users/{id}/permissions | Assign permission to user | Yes | {"permission"} -> Updated user |
| DELETE | /users/{id}/permissions/{permission} | Remove permission from user | Yes | Success message |

## Database Schema

**Tables**:

1. **users** - User accounts
   - id (PK)
   - name
   - email (unique)
   - password (hashed)
   - email_verified_at
   - remember_token
   - timestamps

2. **roles** - System roles (Spatie)
   - id (PK)
   - name (unique per guard)
   - guard_name
   - timestamps

3. **permissions** - System permissions (Spatie)
   - id (PK)
   - name (unique per guard)
   - guard_name
   - timestamps

4. **model_has_roles** - User-role assignments
   - role_id (FK)
   - model_type
   - model_uuid

5. **model_has_permissions** - Direct user-permission assignments
   - permission_id (FK)
   - model_type
   - model_uuid

6. **role_has_permissions** - Role-permission assignments
   - permission_id (FK)
   - role_id (FK)

7. **cache**, **jobs** - Laravel infrastructure tables

**Relationships**:
- User -> Roles (many-to-many via model_has_roles)
- User -> Permissions (many-to-many via model_has_permissions)
- Role -> Permissions (many-to-many via role_has_permissions)

## RabbitMQ Integration

**Events Consumed**:
- `auth.validate.request` - Token validation requests from other services
- `user.create.request` - User creation from other services

**Events Published**:
- `auth.validated` - Token validation result
- `user.created` - New user registration event
- `user.logged_in` - User login event
- `user.logged_out` - User logout event
- `role.assigned` - Role assigned to user
- `permission.assigned` - Permission assigned to user/role

**Message Format Example**:
```json
{
  "event": "user.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "user_id": 123,
    "email": "user@example.com",
    "name": "John Doe",
    "roles": ["customer"]
  }
}
```

## JWT Token Structure

**Token Payload**:
```json
{
  "iss": "http://auth-service:8000/api/login",
  "iat": 1234567890,
  "exp": 1234571490,
  "nbf": 1234567890,
  "jti": "unique-token-id",
  "sub": "user-id",
  "prv": "hash",
  "role": "admin",
  "email": "user@example.com"
}
```

**Token Configuration**:
- Algorithm: HS256
- TTL: 60 minutes (configurable)
- Refresh TTL: 20160 minutes (2 weeks)
- Secret: JWT_SECRET environment variable

## Authorization Model

**Default Roles**:
- `admin` - Full system access
- `manager` - Business operations access
- `customer` - Customer-facing features
- `guest` - Limited read access

**Permission Structure**:
- Format: `resource.action` (e.g., `products.create`, `orders.view`)
- Granular permissions for each resource
- Wildcard support: `products.*` for all product operations

**Middleware Integration**:
- Services use Shared\Middleware\JWTAuthMiddleware
- Token validated via auth-service /validate-token endpoint
- Roles/permissions cached in token payload

## Environment Variables

```bash
# Application
APP_NAME=auth-service
APP_ENV=local
APP_PORT=8000

# Database
DB_CONNECTION=mysql
DB_HOST=auth-mysql
DB_PORT=3306
DB_DATABASE=auth_db
DB_USERNAME=auth_user
DB_PASSWORD=auth_password

# JWT Configuration
JWT_SECRET=your-secret-key
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=auth_exchange
RABBITMQ_QUEUE=auth_queue
```

## Deployment

**Docker Configuration**:
```yaml
Service: auth-service
Port Mapping: 8000:8000
Database: auth-mysql (port 3307 external)
Depends On: auth-mysql, rabbitmq
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 2 replicas minimum
- CPU Request: 100m, Limit: 300m
- Memory Request: 256Mi, Limit: 512Mi
- Service Type: ClusterIP
- ConfigMap: JWT configuration
- Secret: JWT_SECRET, database credentials

**Health Check Configuration**:
- Liveness Probe: GET /health (10s interval)
- Readiness Probe: Database connection test
- Startup Probe: 30s timeout for migrations

## Security Considerations

**Password Security**:
- Bcrypt hashing (Laravel default)
- Minimum password length: 8 characters
- Password validation rules enforced

**Token Security**:
- Short-lived access tokens (60 min)
- Refresh token rotation
- Token blacklisting on logout
- Secret key rotation supported

**API Security**:
- Rate limiting on login/register endpoints
- CORS configuration
- Input validation and sanitization
- SQL injection protection (Eloquent ORM)

## Performance Optimization

**Caching Strategy**:
- Role and permission queries cached
- Token validation results cached (30s TTL)
- Database connection pooling

**Database Optimization**:
- Indexes on email, name columns
- Composite indexes for Spatie permission tables
- Query optimization for role/permission checks

## Integration with Other Services

All services validate JWT tokens via:
1. Include token in Authorization header: `Bearer {token}`
2. Middleware extracts and validates token
3. User ID and roles extracted from token payload
4. Services can make additional permission checks if needed

**Example Token Validation Flow**:
```
1. Client -> Gateway with Bearer token
2. Gateway -> Products Service via RabbitMQ
3. Products Service extracts token
4. Products Service validates token structure
5. Products Service checks permissions (from token or cache)
6. Products Service processes request
```

## Monitoring and Observability

**Metrics to Track**:
- Login success/failure rate
- Token validation throughput
- Password reset requests
- Role/permission changes
- Database query performance

**Logging**:
- Authentication attempts (success/failure)
- Token generation and refresh events
- Role and permission modifications
- Security events (suspicious activity)

## Future Enhancements

- Multi-factor authentication (MFA/2FA)
- OAuth2/OpenID Connect integration
- Social login (Google, Facebook, etc.)
- Session management and device tracking
- Password policy enforcement
- Account lockout after failed attempts
- Audit log for all auth events
