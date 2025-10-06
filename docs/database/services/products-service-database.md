# Products Service Database Documentation

## Table of Contents
- [Overview](#overview)
- [Database Information](#database-information)
- [Entity Relationship Diagram](#entity-relationship-diagram)
- [Table Schemas](#table-schemas)
- [Architecture Details](#architecture-details)
- [MinIO Integration](#minio-integration)
- [Events Published](#events-published)
- [Cross-Service References](#cross-service-references)
- [Indexes and Performance](#indexes-and-performance)

## Overview

The products-service database (`products_service_db`) manages the complete product catalog including inventory, classification taxonomy, attributes, characteristics, and image metadata. This service provides the foundation for e-commerce product management with complex relationships for categorization and multi-dimensional product variants.

**Service:** products-service
**Database:** products_service_db
**External Port:** 3307
**Total Tables:** 15 (11 business, 3 pivot, 1 media)

**Key Capabilities:**
- Multi-level product taxonomy (types, categories, catalogs)
- Hierarchical category and catalog structures
- Product variants via attributes and characteristics
- Brand management
- VAT rate configuration
- Stock tracking
- Multi-image support with MinIO object storage
- Soft deletes for products, brands, and catalogs

## Database Information

### Connection Details
```bash
Host: localhost (in Docker network: mysql-products)
Port: 3307 (external), 3306 (internal)
Database: products_service_db
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
Engine: InnoDB
```

### Environment Configuration
```bash
DB_CONNECTION=mysql
DB_HOST=mysql-products
DB_PORT=3306
DB_DATABASE=products_service_db
DB_USERNAME=products_user
DB_PASSWORD=products_pass
```

## Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                     PRODUCTS SERVICE DATABASE                       │
│                    products_service_db (15 tables)                  │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│                      TAXONOMY & CLASSIFICATION                       │
└──────────────────────────────────────────────────────────────────────┘

    ┌───────────┐
    │    vat    │
    ├───────────┤
    │ id    PK  │
    │ name      │
    │ value_    │ decimal(5,2) -- VAT percentage
    └─────┬─────┘
          │
          │ id_1 (vat_id)
          ▼
    ┌───────────┐           ┌────────────────┐
    │   types   │           │  categories    │
    ├───────────┤           ├────────────────┤
    │ id    PK  │           │ id         PK  │
    │ name      │           │ name           │
    │ id_1  FK  │─────VAT   │ id_1       FK  │─┐
    │ created_at│           │ created_at     │ │ Self-referencing
    │ updated_at│           │ updated_at     │ │ (parent category)
    └─────┬─────┘           └──────┬─────────┘ │
          │                        │           │
          │                        └───────────┘
          │
    ┌─────┴──────┐           ┌────────────────┐
    │  catalogs  │           │    brands      │
    ├────────────┤           ├────────────────┤
    │ id     PK  │           │ id         PK  │
    │ name       │           │ name           │
    │ id_1   FK  │─┐         │ website        │
    │ created_at │ │         │ created_at     │
    │ updated_at │ │         │ updated_at     │
    │ deleted_at │ │         │ deleted_at     │
    └────────────┘ │         └────────┬───────┘
                   │                  │
         Self-ref  │                  │
         (parent)  │                  │
                   │                  │
                   └──────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│                         CORE PRODUCTS                                │
└──────────────────────────────────────────────────────────────────────┘

                ┌────────────────────────────────┐
                │          products              │
                ├────────────────────────────────┤
                │ id              PK             │
                │ name                           │
                │ ref             UNIQUE         │
                │ price_ht        decimal(10,2)  │
                │ stock           int            │
                │ id_1            FK (brand)     │───────────┐
                │ created_at                     │           │
                │ updated_at                     │           │
                │ deleted_at                     │           │
                └────────┬───────────────────────┘           │
                         │                                   │
         ┌───────────────┼───────────────┬──────────────┐   │
         │               │               │              │   │
         ▼               ▼               ▼              │   │
┌────────────────┐ ┌─────────────┐ ┌──────────────┐   │   │
│ product_types  │ │product_     │ │product_      │   │   │
│                │ │categories   │ │catalogs      │   │   │
├────────────────┤ ├─────────────┤ ├──────────────┤   │   │
│ id         PK  │ │ id      PK  │ │ id       PK  │   │   │
│ product_id FK  │ │ product_id  │ │ product_id   │   │   │
│ type_id    FK  │ │ FK          │ │ FK           │   │   │
│ created_at     │ │ category_id │ │ catalog_id   │   │   │
│ updated_at     │ │ FK          │ │ FK           │   │   │
└────────────────┘ │ created_at  │ │ created_at   │   │   │
                   │ updated_at  │ │ updated_at   │   │   │
                   └─────────────┘ └──────────────┘   │   │
                                                       │   │
                           ┌───────────────────────────┘   │
                           │                               │
                           ▼                               │
                   ┌───────────────┐                       │
                   │ product_images│                       │
                   ├───────────────┤                       │
                   │ id         PK │                       │
                   │ product_id FK │───────────────────────┘
                   │ original_url  │ -- MinIO path
                   │ thumbnail_url │
                   │ medium_url    │
                   │ filename      │
                   │ type          │ ENUM(main/gallery/thumbnail)
                   │ alt_text      │
                   │ position      │ int
                   │ size          │ bigint (bytes)
                   │ mime_type     │
                   │ created_at    │
                   │ updated_at    │
                   └───────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│                   ATTRIBUTES & CHARACTERISTICS                       │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────┐              ┌────────────────────────┐
│ attribute_groups │              │ characteristic_groups  │
├──────────────────┤              ├────────────────────────┤
│ id           PK  │              │ id                 PK  │
│ name             │              │ name                   │
│ created_at       │              │ created_at             │
│ updated_at       │              │ updated_at             │
└────────┬─────────┘              └────────┬───────────────┘
         │                                 │
         │ attribute_group_id              │ id_1 (group_id)
         ▼                                 ▼
┌──────────────────┐              ┌────────────────────────┐
│   attributes     │              │ related_               │
│                  │              │ characteristics        │
├──────────────────┤              ├────────────────────────┤
│ id           PK  │              │ id                 PK  │
│ value_           │              │ name                   │
│ stock        int │              │ id_1               FK  │───┐
│ product_id   FK  │──┐           │ created_at             │   │
│ attribute_       │  │           │ updated_at             │   │
│  group_id    FK  │  │           └────────┬───────────────┘   │
│ created_at       │  │                    │                   │
│ updated_at       │  │                    │ related_          │
└──────────────────┘  │                    │  characteristic   │
                      │                    │  _id              │
                      │                    ▼                   │
                      │           ┌────────────────────────┐   │
                      │           │  characteristics       │   │
                      │           ├────────────────────────┤   │
                      │           │ id                 PK  │   │
                      │           │ value_                 │   │
                      └───────────│ product_id         FK  │   │
                                  │ related_               │   │
                                  │  characteristic_   FK  │───┘
                                  │  id                    │
                                  │ created_at             │
                                  │ updated_at             │
                                  └────────────────────────┘

LEGEND:
────────  Relationship / Foreign Key
PK        Primary Key
FK        Foreign Key
UNIQUE    Unique Constraint
```

## Table Schemas

### 1. vat
Tax rate configuration table.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | VAT rate ID |
| name | VARCHAR(255) | NOT NULL | VAT rate name (e.g., "Standard", "Reduced") |
| value_ | DECIMAL(5,2) | NOT NULL | VAT percentage (e.g., 20.00 for 20%) |

**Indexes:**
- PRIMARY KEY (id)

**Business Rules:**
- No timestamps (static reference data)
- Used by types table to determine applicable VAT rates
- Values typically: 0.00, 5.50, 10.00, 20.00 (FR standard rates)

---

### 2. brands
Product brand/manufacturer management.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Brand ID |
| name | VARCHAR(255) | NOT NULL | Brand name |
| website | VARCHAR(255) | NULLABLE | Brand website URL |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |
| deleted_at | TIMESTAMP | NULLABLE | Soft delete timestamp |

**Indexes:**
- PRIMARY KEY (id)
- INDEX (name, deleted_at)

**Business Rules:**
- Soft deletes enabled (deleted_at)
- Referenced by products.id_1 (brand_id)
- Website URL optional for brand information

---

### 3. types
Product type taxonomy with VAT association.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Type ID |
| name | VARCHAR(255) | NOT NULL | Type name |
| id_1 | BIGINT UNSIGNED | NULLABLE, FK | VAT rate reference (vat.id) |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- id_1 → vat(id) ON DELETE SET NULL

**Indexes:**
- PRIMARY KEY (id)
- INDEX (name, id_1)

**Business Rules:**
- Each type can have one VAT rate
- VAT reference nullable (set null on delete)
- Many-to-many with products via product_types

**Examples:**
- Electronics, Clothing, Books, Food, etc.

---

### 4. categories
Hierarchical product categorization.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Category ID |
| name | VARCHAR(255) | NOT NULL | Category name |
| id_1 | BIGINT UNSIGNED | NULLABLE, FK | Parent category (categories.id) |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- id_1 → categories(id) ON DELETE CASCADE (self-referencing)

**Indexes:**
- PRIMARY KEY (id)
- INDEX (name, id_1)

**Business Rules:**
- Self-referencing for category hierarchy
- id_1 NULL = root category
- Cascade delete removes child categories
- Many-to-many with products via product_categories

**Example Hierarchy:**
```
Electronics (id_1=NULL)
  ├─ Computers (id_1=1)
  │   ├─ Laptops (id_1=2)
  │   └─ Desktops (id_1=2)
  └─ Mobile (id_1=1)
      ├─ Phones (id_1=3)
      └─ Tablets (id_1=3)
```

---

### 5. catalogs
Product catalog/collection management with hierarchy.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Catalog ID |
| name | VARCHAR(255) | NOT NULL | Catalog name |
| id_1 | BIGINT UNSIGNED | NULLABLE, FK | Parent catalog (catalogs.id) |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |
| deleted_at | TIMESTAMP | NULLABLE | Soft delete timestamp |

**Foreign Keys:**
- id_1 → catalogs(id) ON DELETE CASCADE (self-referencing)

**Indexes:**
- PRIMARY KEY (id)
- INDEX (name, deleted_at)

**Business Rules:**
- Self-referencing for catalog hierarchy
- Soft deletes enabled
- Many-to-many with products via product_catalogs
- Used for seasonal collections, promotions, featured sets

**Examples:**
- "Winter 2024", "Best Sellers", "New Arrivals"

---

### 6. attribute_groups
Grouping for product attributes.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Attribute group ID |
| name | VARCHAR(255) | NOT NULL | Group name |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY (id)
- INDEX (name)

**Business Rules:**
- Organizes related attributes
- Referenced by attributes table

**Examples:**
- "Size", "Color", "Material", "Capacity"

---

### 7. characteristic_groups
Grouping for product characteristics.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Characteristic group ID |
| name | VARCHAR(255) | NOT NULL | Group name |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY (id)
- INDEX (name)

**Business Rules:**
- Organizes related characteristics
- Referenced by related_characteristics table

**Examples:**
- "Technical Specs", "Dimensions", "Performance"

---

### 8. products (CORE)
Central product entity with inventory and pricing.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Product ID |
| name | VARCHAR(255) | NOT NULL | Product name |
| ref | VARCHAR(255) | NOT NULL, UNIQUE | Product reference/SKU |
| price_ht | DECIMAL(10,2) | NOT NULL | Price excluding tax |
| stock | INTEGER | NOT NULL, DEFAULT 0 | Available stock quantity |
| id_1 | BIGINT UNSIGNED | NULLABLE, FK | Brand reference (brands.id) |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |
| deleted_at | TIMESTAMP | NULLABLE | Soft delete timestamp |

**Foreign Keys:**
- id_1 → brands(id) ON DELETE SET NULL

**Indexes:**
- PRIMARY KEY (id)
- UNIQUE (ref)
- INDEX (ref, deleted_at)
- INDEX (name, deleted_at)
- INDEX (stock)
- INDEX (price_ht)

**Business Rules:**
- Soft deletes enabled
- ref must be unique (SKU/product reference)
- price_ht stored as decimal for precision
- Stock tracked at product level (base) + attribute level (variants)
- Brand reference optional

**Model Relationships:**
- belongsTo: Brand (via id_1)
- belongsToMany: Type, Category, Catalog (via pivot tables)
- hasMany: Attribute, Characteristic, ProductImage

---

### 9. product_images
Product image metadata with MinIO storage references.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Image ID |
| product_id | BIGINT UNSIGNED | NOT NULL, FK | Product reference (products.id) |
| original_url | VARCHAR(255) | NOT NULL | Full-size image URL (MinIO) |
| thumbnail_url | VARCHAR(255) | NULLABLE | Thumbnail URL (MinIO) |
| medium_url | VARCHAR(255) | NULLABLE | Medium-size URL (MinIO) |
| filename | VARCHAR(255) | NOT NULL | Original filename |
| type | ENUM | NOT NULL | Image type (main/gallery/thumbnail) |
| alt_text | VARCHAR(255) | NULLABLE | Accessibility alt text |
| position | INTEGER | NOT NULL, DEFAULT 0 | Display order |
| size | BIGINT | NOT NULL | File size in bytes |
| mime_type | VARCHAR(255) | NOT NULL | MIME type (image/jpeg, etc.) |
| created_at | TIMESTAMP | NULLABLE | Upload timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- product_id → products(id) ON DELETE CASCADE

**Indexes:**
- PRIMARY KEY (id)
- INDEX (product_id, type)
- INDEX (product_id, position)

**Business Rules:**
- Cascade delete when product deleted
- type ENUM values: 'main', 'gallery', 'thumbnail'
- position determines display order
- original_url points to MinIO 'products' bucket
- Multiple sizes generated for responsive images

**Model Scopes:**
- scopeMain(): Filter main images
- scopeGallery(): Filter gallery images

---

### 10. attributes
Product variants with stock tracking (e.g., size, color).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Attribute ID |
| value_ | VARCHAR(255) | NOT NULL | Attribute value |
| stock | INTEGER | NOT NULL, DEFAULT 0 | Stock for this variant |
| product_id | BIGINT UNSIGNED | NULLABLE, FK | Product reference (products.id) |
| attribute_group_id | BIGINT UNSIGNED | NULLABLE, FK | Group reference (attribute_groups.id) |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- product_id → products(id) ON DELETE CASCADE
- attribute_group_id → attribute_groups(id) ON DELETE SET NULL

**Indexes:**
- PRIMARY KEY (id)
- INDEX (product_id, attribute_group_id)
- INDEX (stock)

**Business Rules:**
- Cascade delete with product
- Stock tracked per attribute variant
- Grouped by attribute_group for organization

**Example:**
```
Product: "T-Shirt"
  Attribute Group: "Size"
    - value_="S", stock=10
    - value_="M", stock=15
    - value_="L", stock=8
  Attribute Group: "Color"
    - value_="Red", stock=12
    - value_="Blue", stock=20
```

---

### 11. related_characteristics
Named characteristic definitions linked to groups.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Related characteristic ID |
| name | VARCHAR(255) | NOT NULL | Characteristic name |
| id_1 | BIGINT UNSIGNED | NULLABLE, FK | Group reference (characteristic_groups.id) |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- id_1 → characteristic_groups(id) ON DELETE SET NULL

**Indexes:**
- PRIMARY KEY (id)
- INDEX (name, id_1)

**Business Rules:**
- Defines reusable characteristic templates
- Grouped for organization
- Referenced by characteristics table

**Examples:**
- Group: "Technical Specs"
  - "Processor", "RAM", "Storage", "Screen Size"
- Group: "Dimensions"
  - "Height", "Width", "Depth", "Weight"

---

### 12. characteristics
Product characteristic values.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Characteristic ID |
| value_ | VARCHAR(255) | NOT NULL | Characteristic value |
| product_id | BIGINT UNSIGNED | NULLABLE, FK | Product reference (products.id) |
| related_characteristic_id | BIGINT UNSIGNED | NULLABLE, FK | Template reference (related_characteristics.id) |
| created_at | TIMESTAMP | NULLABLE | Record creation timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- product_id → products(id) ON DELETE CASCADE
- related_characteristic_id → related_characteristics(id) ON DELETE SET NULL

**Indexes:**
- PRIMARY KEY (id)
- INDEX (product_id, related_characteristic_id)

**Business Rules:**
- Cascade delete with product
- Links to related_characteristics for structure

**Example:**
```
Product: "Laptop XYZ"
  Related Characteristic: "Processor"
    - value_="Intel Core i7-12700H"
  Related Characteristic: "RAM"
    - value_="16GB DDR4"
  Related Characteristic: "Storage"
    - value_="512GB NVMe SSD"
```

---

### 13. product_types (Pivot)
Many-to-many relationship between products and types.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Pivot ID |
| product_id | BIGINT UNSIGNED | NOT NULL, FK | Product reference |
| type_id | BIGINT UNSIGNED | NOT NULL, FK | Type reference |
| created_at | TIMESTAMP | NULLABLE | Association timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- product_id → products(id) ON DELETE CASCADE
- type_id → types(id) ON DELETE CASCADE

**Indexes:**
- PRIMARY KEY (id)
- UNIQUE (product_id, type_id)

**Business Rules:**
- Unique constraint prevents duplicate associations
- Cascade delete on both sides

---

### 14. product_categories (Pivot)
Many-to-many relationship between products and categories.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Pivot ID |
| product_id | BIGINT UNSIGNED | NOT NULL, FK | Product reference |
| category_id | BIGINT UNSIGNED | NOT NULL, FK | Category reference |
| created_at | TIMESTAMP | NULLABLE | Association timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- product_id → products(id) ON DELETE CASCADE
- category_id → categories(id) ON DELETE CASCADE

**Indexes:**
- PRIMARY KEY (id)
- UNIQUE (product_id, category_id)

**Business Rules:**
- Unique constraint prevents duplicate associations
- Cascade delete on both sides
- Allows products in multiple categories

---

### 15. product_catalogs (Pivot)
Many-to-many relationship between products and catalogs.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Pivot ID |
| product_id | BIGINT UNSIGNED | NOT NULL, FK | Product reference |
| catalog_id | BIGINT UNSIGNED | NOT NULL, FK | Catalog reference |
| created_at | TIMESTAMP | NULLABLE | Association timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- product_id → products(id) ON DELETE CASCADE
- catalog_id → catalogs(id) ON DELETE CASCADE

**Indexes:**
- PRIMARY KEY (id)
- UNIQUE (product_id, catalog_id)

**Business Rules:**
- Unique constraint prevents duplicate associations
- Cascade delete on both sides
- Allows products in multiple catalogs/collections

## Architecture Details

### Product Taxonomy Design

The products-service implements a flexible multi-dimensional classification system:

1. **Types**: Broad product categorization with VAT association
   - Examples: Electronics, Clothing, Books
   - Each type can have a default VAT rate

2. **Categories**: Hierarchical categorization (unlimited depth)
   - Self-referencing parent-child relationships
   - Root categories have id_1=NULL
   - Supports deep nesting: Electronics > Computers > Laptops > Gaming

3. **Catalogs**: Marketing-oriented collections
   - Hierarchical structure for collection organization
   - Examples: Seasonal, Featured, Promotional sets
   - Independent of categories (cross-cutting concerns)

4. **Brands**: Manufacturer/brand associations
   - One-to-many with products
   - Soft deletes preserve brand history

### Product Variants Architecture

**Attributes vs Characteristics:**

**Attributes** (Stock-Tracked Variants):
- Represent purchasable variations (size, color)
- Each attribute has independent stock
- Grouped by attribute_groups
- Examples: Size (S/M/L), Color (Red/Blue)

**Characteristics** (Descriptive Properties):
- Describe product features (non-purchasable)
- No stock tracking
- Linked to related_characteristics for structure
- Examples: Processor type, dimensions, weight

### Soft Deletes

Enabled on:
- **products**: Preserve order history
- **brands**: Maintain referential integrity
- **catalogs**: Keep collection history

Not enabled on:
- Reference tables (vat, types, categories)
- Relationship tables (attributes, characteristics)
- Pivot tables

## MinIO Integration

### Products Bucket

The products-service integrates with MinIO object storage for image management.

**Bucket:** `products`
**Access:** S3-compatible API via AWS SDK
**Credentials:**
```bash
MINIO_ENDPOINT=minio:9000
MINIO_ROOT_USER=admin
MINIO_ROOT_PASSWORD=adminpass123
MINIO_BUCKET_PRODUCTS=products
```

### Image Storage Pattern

```php
// Shared MinioService usage in products-service
use Shared\Services\MinioService;

$minioService = new MinioService('products');

// Upload image
$result = $minioService->uploadFile($file, 'products/product-123/image.jpg');
// Returns: ['url' => 'http://minio:9000/products/products/product-123/image.jpg']

// Generate presigned URL (temporary access)
$presignedUrl = $minioService->getPresignedUrl('products/product-123/image.jpg', 3600);
// Valid for 1 hour

// Delete image
$minioService->deleteFile('products/product-123/image.jpg');
```

### Image Variants

product_images table stores multiple resolutions:

1. **original_url**: Full-size image (stored in MinIO)
2. **medium_url**: Optimized for product listing pages
3. **thumbnail_url**: Small preview for cart/search

**Path Structure:**
```
products/
  ├─ [product-ref]/
  │   ├─ main.jpg          (type='main')
  │   ├─ main-medium.jpg
  │   ├─ main-thumb.jpg
  │   ├─ gallery-1.jpg     (type='gallery')
  │   ├─ gallery-2.jpg
  │   └─ ...
```

### Image Processing Workflow

1. **Upload**: Original image uploaded to MinIO
2. **Processing**: Generate thumbnail and medium variants
3. **Storage**: All variants stored in MinIO
4. **Metadata**: URLs and metadata saved to product_images table
5. **Retrieval**: Presigned URLs generated for client access

**Example product_images Record:**
```json
{
  "id": 1,
  "product_id": 42,
  "original_url": "http://minio:9000/products/PROD-001/main.jpg",
  "thumbnail_url": "http://minio:9000/products/PROD-001/main-thumb.jpg",
  "medium_url": "http://minio:9000/products/PROD-001/main-medium.jpg",
  "filename": "laptop-image.jpg",
  "type": "main",
  "alt_text": "Professional Laptop XYZ Front View",
  "position": 0,
  "size": 2458624,
  "mime_type": "image/jpeg"
}
```

## Events Published

The products-service publishes events to RabbitMQ for inter-service communication.

### Event Schema

All events follow standard message format:
```json
{
  "event": "product.created",
  "timestamp": "2025-10-03T14:30:00Z",
  "data": {
    "product_id": 42,
    "ref": "PROD-001",
    "name": "Product Name",
    "price_ht": 99.99,
    "stock": 100
  }
}
```

### 1. ProductCreated
**Queue:** products.created
**Published:** When new product successfully created

**Payload:**
```json
{
  "event": "product.created",
  "data": {
    "product_id": 42,
    "ref": "PROD-001",
    "name": "Professional Laptop",
    "price_ht": 899.00,
    "stock": 50,
    "brand_id": 5,
    "created_at": "2025-10-03T14:30:00Z"
  }
}
```

**Consumers:**
- baskets-service: Update available products
- orders-service: Sync product catalog
- search-service: Index new product (future)

---

### 2. ProductUpdated
**Queue:** products.updated
**Published:** When product details modified

**Payload:**
```json
{
  "event": "product.updated",
  "data": {
    "product_id": 42,
    "ref": "PROD-001",
    "name": "Professional Laptop (Updated)",
    "price_ht": 849.00,
    "stock": 50,
    "changes": ["name", "price_ht"],
    "updated_at": "2025-10-03T15:45:00Z"
  }
}
```

**Consumers:**
- baskets-service: Update product info in active carts
- orders-service: Sync product catalog
- search-service: Reindex product

---

### 3. StockUpdated
**Queue:** products.stock.updated
**Published:** When product or attribute stock changes

**Payload:**
```json
{
  "event": "stock.updated",
  "data": {
    "product_id": 42,
    "ref": "PROD-001",
    "stock": 45,
    "previous_stock": 50,
    "change": -5,
    "reason": "order_placed",
    "attribute_id": null,
    "updated_at": "2025-10-03T16:00:00Z"
  }
}
```

**Consumers:**
- baskets-service: Validate cart availability
- orders-service: Stock reservation system
- notifications-service: Low stock alerts

**Attribute Stock Update:**
```json
{
  "event": "stock.updated",
  "data": {
    "product_id": 42,
    "attribute_id": 15,
    "attribute_value": "Size: M",
    "stock": 8,
    "previous_stock": 10,
    "change": -2
  }
}
```

---

### 4. ProductDeleted
**Queue:** products.deleted
**Published:** When product soft deleted

**Payload:**
```json
{
  "event": "product.deleted",
  "data": {
    "product_id": 42,
    "ref": "PROD-001",
    "deleted_at": "2025-10-03T17:00:00Z",
    "reason": "discontinued"
  }
}
```

**Consumers:**
- baskets-service: Remove from active carts
- orders-service: Mark as unavailable
- search-service: Remove from index

---

### 5. ProductImageAdded
**Queue:** products.images.added
**Published:** When new image uploaded

**Payload:**
```json
{
  "event": "product.image.added",
  "data": {
    "product_id": 42,
    "image_id": 7,
    "type": "gallery",
    "original_url": "http://minio:9000/products/PROD-001/gallery-3.jpg",
    "created_at": "2025-10-03T14:35:00Z"
  }
}
```

**Consumers:**
- cdn-service: Invalidate cache (future)
- search-service: Update product preview

### RabbitMQ Configuration

**Exchange:** products_exchange (topic)
**Routing Keys:**
- product.created
- product.updated
- product.deleted
- product.stock.updated
- product.image.added

**Consumer Queues:**
- baskets-service: products.events.baskets
- orders-service: products.events.orders
- search-service: products.events.search

## Cross-Service References

### Referenced BY Other Services

#### baskets-service
```sql
-- basket_items table
CREATE TABLE basket_items (
    product_id BIGINT UNSIGNED,  -- References products.id
    product_ref VARCHAR(255),     -- Cache of products.ref
    price_at_addition DECIMAL(10,2), -- Cache of products.price_ht
    -- NO foreign key constraint (different database)
);
```

**Synchronization:**
- Listen to ProductUpdated: Update cached product_ref, price
- Listen to StockUpdated: Validate availability
- Listen to ProductDeleted: Remove from carts

---

#### orders-service
```sql
-- order_items table
CREATE TABLE order_items (
    product_id BIGINT UNSIGNED,  -- References products.id
    product_ref VARCHAR(255),     -- Cache of products.ref
    product_name VARCHAR(255),    -- Cache of products.name at purchase
    price_ht DECIMAL(10,2),       -- Cache of products.price_ht at purchase
    -- NO foreign key constraint (different database)
);
```

**Synchronization:**
- Listen to ProductUpdated: Sync catalog for new orders
- Listen to StockUpdated: Validate order placement
- Snapshot product data at order creation (price, name preserved)

---

### No Direct References TO Other Services

The products-service is relatively independent:
- Does NOT reference user_id (no user-specific data)
- Does NOT reference address_id
- Does NOT reference order_id

**Exception:** Future features might add:
- Vendor management (user_id for marketplace vendors)
- Product reviews (user_id for reviewers)

## Indexes and Performance

### Strategic Indexes

#### products Table
```sql
INDEX (ref, deleted_at)       -- Product lookup by reference
INDEX (name, deleted_at)      -- Search by product name
INDEX (stock)                 -- Low stock queries
INDEX (price_ht)              -- Price range filtering
```

**Query Optimization:**
```sql
-- Fast lookup by SKU (ref)
SELECT * FROM products WHERE ref = 'PROD-001' AND deleted_at IS NULL;

-- Price range search
SELECT * FROM products
WHERE price_ht BETWEEN 50.00 AND 150.00
  AND deleted_at IS NULL;

-- Low stock alert
SELECT * FROM products WHERE stock < 10 AND deleted_at IS NULL;
```

---

#### product_images Table
```sql
INDEX (product_id, type)      -- Get main image
INDEX (product_id, position)  -- Ordered gallery
```

**Query Optimization:**
```sql
-- Get main product image
SELECT * FROM product_images
WHERE product_id = 42 AND type = 'main'
LIMIT 1;

-- Gallery images in order
SELECT * FROM product_images
WHERE product_id = 42 AND type = 'gallery'
ORDER BY position ASC;
```

---

#### attributes Table
```sql
INDEX (product_id, attribute_group_id)  -- Get all sizes/colors
INDEX (stock)                           -- Available variants
```

**Query Optimization:**
```sql
-- Get all available sizes for product
SELECT * FROM attributes
WHERE product_id = 42
  AND attribute_group_id = 3  -- Size group
  AND stock > 0;
```

---

### Composite Indexes

#### categories Table
```sql
INDEX (name, id_1)  -- Category lookup with parent
```

**Benefits:**
- Fast category tree traversal
- Efficient parent-child queries
- Name-based search with hierarchy

---

#### Pivot Tables
```sql
-- product_types
UNIQUE (product_id, type_id)

-- product_categories
UNIQUE (product_id, category_id)

-- product_catalogs
UNIQUE (product_id, catalog_id)
```

**Benefits:**
- Prevent duplicate associations
- Fast existence checks
- Efficient JOIN operations

### Performance Recommendations

1. **Product Queries:**
   - Always include deleted_at IS NULL for active products
   - Use ref over id for external references (unique, indexed)

2. **Stock Checks:**
   - Use stock index for availability queries
   - Consider caching frequently checked products

3. **Image Loading:**
   - Load main image separately (type index)
   - Lazy load gallery images (position index)

4. **Taxonomy Queries:**
   - Cache category trees (rarely change)
   - Use composite indexes for filtered searches

5. **Attribute Filtering:**
   - Filter by attribute_group_id first (more selective)
   - Stock filtering reduces result set early

---

**Document Version:** 1.0
**Last Updated:** 2025-10-03
**Database Version:** MySQL 8.0
**Laravel Version:** 12.x
