# Products Service

## Service Overview

**Purpose**: Product catalog management including products, categories, brands, types, catalogs, and inventory.

**Port**: 8001
**Database**: products_db (MySQL 8.0)
**External Port**: 3308 (for debugging)
**Dependencies**: Auth service (for admin operations), MinIO (for product images)

**Storage Integration**: MinIO object storage for product images (bucket: products)

## Responsibilities

- Product catalog management (CRUD operations)
- Category hierarchy management
- Brand and type classification
- Product attributes and characteristics
- Product images storage (MinIO integration)
- Inventory tracking
- VAT rate management
- Product search and filtering
- Catalog organization

## API Endpoints

### Public Endpoints (No Auth Required)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| GET | /health | Service health check | {"status":"healthy","service":"products-service"} |
| GET | /products | List all products | Query params: page, per_page, category, brand, type |
| GET | /products/search | Search products | Query: q, filters |
| GET | /products/{id} | Get product details | Product object with relationships |
| GET | /categories | List categories | Category tree structure |
| GET | /categories/{id} | Get category details | Category with products count |
| GET | /categories/{id}/products | Products in category | Paginated product list |
| GET | /brands | List brands | [{"id","name","logo"}] |
| GET | /brands/{id} | Get brand details | Brand with metadata |
| GET | /brands/{id}/products | Products by brand | Paginated product list |
| GET | /types | List product types | [{"id","name","description"}] |
| GET | /types/{id} | Get type details | Type with characteristics |
| GET | /types/{id}/products | Products by type | Paginated product list |
| GET | /catalogs | List catalogs | [{"id","name","description"}] |
| GET | /catalogs/{id} | Get catalog details | Catalog with products |
| GET | /catalogs/{id}/products | Products in catalog | Paginated product list |
| GET | /vat | List VAT rates | [{"id","rate","country"}] |
| GET | /vat/{id} | Get VAT rate details | VAT rate object |

### Admin Endpoints (Auth Required)

| Method | Endpoint | Description | Request/Response |
|--------|----------|-------------|------------------|
| POST | /admin/products | Create product | Product data -> Created product |
| PUT | /admin/products/{id} | Update product | Product data -> Updated product |
| PATCH | /admin/products/{id} | Partial update | Partial data -> Updated product |
| DELETE | /admin/products/{id} | Delete product | Success message |
| POST | /admin/products/{id}/stock | Update stock | {"quantity","operation"} -> Updated stock |
| POST | /admin/categories | Create category | Category data -> Created category |
| PUT | /admin/categories/{id} | Update category | Category data -> Updated category |
| DELETE | /admin/categories/{id} | Delete category | Success message (cascade check) |
| POST | /admin/brands | Create brand | Brand data -> Created brand |
| PUT | /admin/brands/{id} | Update brand | Brand data -> Updated brand |
| DELETE | /admin/brands/{id} | Delete brand | Success message |
| POST | /admin/types | Create type | Type data -> Created type |
| PUT | /admin/types/{id} | Update type | Type data -> Updated type |
| DELETE | /admin/types/{id} | Delete type | Success message |
| POST | /admin/catalogs | Create catalog | Catalog data -> Created catalog |
| PUT | /admin/catalogs/{id} | Update catalog | Catalog data -> Updated catalog |
| DELETE | /admin/catalogs/{id} | Delete catalog | Success message |
| POST | /admin/vat | Create VAT rate | VAT data -> Created VAT |
| PUT | /admin/vat/{id} | Update VAT rate | VAT data -> Updated VAT |
| DELETE | /admin/vat/{id} | Delete VAT rate | Success message |

## Database Schema

**Core Tables**:

1. **products** - Main product table
   - id (PK)
   - name
   - slug (unique)
   - description (text)
   - short_description
   - price (decimal)
   - sale_price (decimal, nullable)
   - cost_price (decimal)
   - sku (unique)
   - barcode
   - quantity (inventory)
   - weight
   - dimensions (JSON)
   - is_active (boolean)
   - is_featured (boolean)
   - brand_id (FK)
   - vat_id (FK)
   - meta_title, meta_description, meta_keywords
   - timestamps, soft_deletes

2. **categories** - Hierarchical categories
   - id (PK)
   - parent_id (FK, nullable - self-referencing)
   - name
   - slug (unique)
   - description
   - image_url
   - order (integer)
   - is_active (boolean)
   - timestamps

3. **brands** - Product brands
   - id (PK)
   - name
   - slug (unique)
   - description
   - logo_url
   - website_url
   - is_active (boolean)
   - timestamps

4. **types** - Product types/classifications
   - id (PK)
   - name
   - slug (unique)
   - description
   - is_active (boolean)
   - timestamps

5. **catalogs** - Product catalog groupings
   - id (PK)
   - name
   - slug (unique)
   - description
   - is_active (boolean)
   - start_date, end_date (nullable)
   - timestamps

6. **vat** - VAT/tax rates
   - id (PK)
   - name
   - rate (decimal)
   - country_code
   - is_default (boolean)
   - timestamps

7. **product_images** - Product image references (MinIO)
   - id (PK)
   - product_id (FK)
   - image_url (MinIO path)
   - thumbnail_url
   - alt_text
   - order (integer)
   - is_primary (boolean)
   - timestamps

8. **attributes** - Product attributes (size, color, etc.)
   - id (PK)
   - attribute_group_id (FK)
   - name
   - value
   - timestamps

9. **characteristics** - Product characteristics/specs
   - id (PK)
   - characteristic_group_id (FK)
   - name
   - value
   - unit
   - timestamps

10. **attribute_groups**, **characteristic_groups** - Grouping tables
    - id (PK)
    - name
    - description
    - timestamps

**Junction Tables**:
- **product_types** - Product to Type (many-to-many)
- **product_categories** - Product to Category (many-to-many)
- **product_catalogs** - Product to Catalog (many-to-many)
- **related_characteristics** - Product to Characteristics

## MinIO Integration

**Bucket**: products
**Image Storage Pattern**:
```
products/
  {product_id}/
    original/
      image_1.jpg
      image_2.jpg
    thumbnails/
      image_1_thumb.jpg
      image_2_thumb.jpg
```

**Image Upload Flow**:
1. Admin uploads product image via API
2. Service validates image (type, size)
3. Image stored in MinIO products bucket
4. Generate thumbnail (async job)
5. Store URLs in product_images table
6. Return presigned URLs for access

**Presigned URL Generation**:
- Expiration: 1 hour for product listing
- Regenerated on each request
- Cached in Redis for performance

## RabbitMQ Integration

**Events Consumed**:
- `product.stock.check` - Stock availability check from basket/order services
- `product.price.request` - Price information request
- `product.details.request` - Full product details request

**Events Published**:
- `product.created` - New product added to catalog
- `product.updated` - Product information modified
- `product.deleted` - Product removed from catalog
- `product.stock.updated` - Inventory level changed
- `product.price.changed` - Price modified (for baskets/orders)
- `product.out_of_stock` - Product inventory depleted

**Message Format Example**:
```json
{
  "event": "product.stock.updated",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "product_id": 123,
    "sku": "PROD-001",
    "previous_quantity": 50,
    "new_quantity": 45,
    "change": -5,
    "reason": "order_placed"
  }
}
```

## Search and Filtering

**Search Capabilities**:
- Full-text search on name, description, SKU
- Category filtering (with hierarchy support)
- Brand filtering
- Type filtering
- Price range filtering
- Attribute-based filtering (size, color, etc.)
- Availability filtering (in stock, on sale, featured)

**Search Optimization**:
- Database indexes on searchable columns
- Future: Elasticsearch integration for advanced search
- Caching of popular search queries

## Inventory Management

**Stock Tracking**:
- Real-time inventory updates
- Configurable low-stock threshold
- Out-of-stock notifications
- Stock reservation for pending orders

**Stock Operations**:
- Increment: Restocking, returns
- Decrement: Sales, damage, loss
- Set: Manual inventory adjustment
- Reserve: Order placement (released on cancellation)

## Environment Variables

```bash
# Application
APP_NAME=products-service
APP_ENV=local
APP_PORT=8001

# Database
DB_CONNECTION=mysql
DB_HOST=products-mysql
DB_PORT=3306
DB_DATABASE=products_db
DB_USERNAME=products_user
DB_PASSWORD=products_password

# MinIO Configuration
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=admin
MINIO_SECRET_KEY=adminpass123
MINIO_BUCKET=products
MINIO_REGION=us-east-1
MINIO_USE_PATH_STYLE=true

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=products_exchange
RABBITMQ_QUEUE=products_queue

# Auth Service
AUTH_SERVICE_URL=http://auth-service:8000
```

## Deployment

**Docker Configuration**:
```yaml
Service: products-service
Port Mapping: 8001:8000
Database: products-mysql (port 3308 external)
Depends On: products-mysql, rabbitmq, minio
Networks: e-commerce-network
Health Check: /health endpoint
```

**Kubernetes Resources**:
- Deployment: 3 replicas minimum
- CPU Request: 200m, Limit: 500m
- Memory Request: 512Mi, Limit: 1Gi
- Service Type: ClusterIP
- PVC: None (uses MinIO for storage)
- ConfigMap: MinIO configuration

**Health Check Configuration**:
- Liveness Probe: GET /health (10s interval)
- Readiness Probe: Database + MinIO connectivity
- Startup Probe: 60s timeout for migrations and seed data

## Performance Optimization

**Caching Strategy**:
- Product listings cached (5 min TTL)
- Category tree cached (15 min TTL)
- Brand and type lists cached (30 min TTL)
- Product detail page cached (2 min TTL)
- MinIO presigned URLs cached (50 min TTL)

**Database Optimization**:
- Indexes on: slug, sku, barcode, brand_id, is_active
- Composite indexes for filtering queries
- Eager loading for relationships
- Query result pagination

**Image Optimization**:
- Lazy loading for product images
- CDN integration (future)
- Responsive image formats (WebP support)
- Thumbnail generation for list views

## Integration with Other Services

**Baskets Service**:
- Product availability checks
- Price validation
- Product detail retrieval

**Orders Service**:
- Stock reservation on order placement
- Stock release on order cancellation
- Product information for order line items

**Search Service** (Future):
- Product indexing for Elasticsearch
- Search result ranking
- Product recommendations

## Monitoring and Observability

**Metrics to Track**:
- Product catalog size (total products)
- Search query performance
- Image upload success rate
- Stock update frequency
- MinIO storage usage
- Database query performance

**Logging**:
- Product CRUD operations
- Stock level changes
- Image upload/delete events
- Search queries and results
- Failed MinIO operations

## Future Enhancements

- Elasticsearch integration for advanced search
- Product recommendations engine
- Related products suggestions
- Product reviews and ratings
- Product variants (size, color combinations)
- Bulk import/export (CSV, Excel)
- Product bundles and kits
- Dynamic pricing rules
- Product comparison feature
- Wishlist integration
