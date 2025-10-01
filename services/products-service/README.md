# Products Service API Documentation

## Overview

The Products Service manages the complete product catalog for the e-commerce platform, implementing a comprehensive schema with support for brands, types, categories, catalogs, VAT rates, and product attributes/characteristics.

## Database Schema

### Core Entities

- **VAT**: VAT rates and tax information
- **Brands**: Product manufacturers/brands
- **Types**: Product type classifications (Smartphone, Laptop, etc.)
- **Categories**: Product categories (Electronics, Mobile Devices, etc.)
- **Catalogs**: Product catalog groupings (Main Catalog, Premium Products, etc.)
- **Products**: Main product entity with relationships to all above
- **Attribute Groups**: Groups for product attributes (Color, Size, etc.)
- **Attributes**: Specific product attributes with stock tracking
- **Characteristic Groups**: Groups for product characteristics
- **Related Characteristics**: Characteristics related to groups
- **Characteristics**: Specific product characteristics

### Relationships

- Products belong to Brands (many-to-one)
- Products have many-to-many relationships with Types, Categories, and Catalogs
- Products have many Attributes and Characteristics
- Attributes belong to Attribute Groups
- Characteristics belong to Related Characteristics

## API Endpoints

### Health Check

```
GET /api/health
```

Returns service health status.

**Response:**
```json
{
  "status": "healthy",
  "service": "products-service"
}
```

### Products

#### List Products
```
GET /api/products
```

**Query Parameters:**
- `search` - Search in product name and reference
- `brand_id` - Filter by brand ID
- `category_id` - Filter by category ID
- `type_id` - Filter by type ID
- `catalog_id` - Filter by catalog ID
- `in_stock` - Filter products in stock (true/false)
- `min_price` - Minimum price filter
- `max_price` - Maximum price filter
- `sort_by` - Sort field (name, ref, price_ht, stock, created_at)
- `sort_order` - Sort direction (asc, desc)
- `per_page` - Items per page (max 50)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "ref": "IPHONE15PRO-128",
      "price_ht": "999.00",
      "stock": 50,
      "created_at": "2025-09-23T22:37:49.000000Z",
      "updated_at": "2025-09-23T22:37:49.000000Z",
      "deleted_at": null,
      "id_1": 1,
      "brand": {
        "id": 1,
        "name": "Apple"
      },
      "types": [...],
      "categories": [...],
      "catalogs": [...],
      "attributes": [...],
      "characteristics": [...]
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 3,
    "last_page": 1,
    "has_more": false
  }
}
```

#### Get Single Product
```
GET /api/products/{id}
```

Returns a single product with all relationships loaded.

#### Search Products
```
GET /api/products/search?q={query}
```

**Query Parameters:**
- `q` - Search query (minimum 2 characters)
- `limit` - Maximum results (max 50)

#### Create Product (Admin)
```
POST /api/admin/products
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "name": "Product Name",
  "ref": "UNIQUE-REF",
  "price_ht": 99.99,
  "stock": 100,
  "id_1": 1,
  "type_ids": [1, 2],
  "category_ids": [1],
  "catalog_ids": [1, 2]
}
```

#### Update Product (Admin)
```
PUT /api/admin/products/{id}
PATCH /api/admin/products/{id}
```

Same body structure as create, all fields optional for PATCH.

#### Delete Product (Admin)
```
DELETE /api/admin/products/{id}
```

#### Update Stock (Admin)
```
POST /api/admin/products/{id}/stock
```

**Body:**
```json
{
  "stock": 50,
  "operation": "set|increment|decrement"
}
```

### Brands

#### List Brands
```
GET /api/brands
```

Returns all brands with product counts.

#### Get Single Brand
```
GET /api/brands/{id}
```

#### Get Brand Products
```
GET /api/brands/{id}/products
```

#### Create Brand (Admin)
```
POST /api/admin/brands
```

**Body:**
```json
{
  "name": "Brand Name"
}
```

#### Update Brand (Admin)
```
PUT /api/admin/brands/{id}
```

#### Delete Brand (Admin)
```
DELETE /api/admin/brands/{id}
```

Note: Cannot delete brands with associated products.

### Categories

#### List Categories
```
GET /api/categories
```

#### Get Single Category
```
GET /api/categories/{id}
```

#### Get Category Products
```
GET /api/categories/{id}/products
```

#### Create Category (Admin)
```
POST /api/admin/categories
```

**Body:**
```json
{
  "name": "Category Name"
}
```

#### Update Category (Admin)
```
PUT /api/admin/categories/{id}
```

#### Delete Category (Admin)
```
DELETE /api/admin/categories/{id}
```

### Types

#### List Types
```
GET /api/types
```

#### Get Single Type
```
GET /api/types/{id}
```

#### Get Type Products
```
GET /api/types/{id}/products
```

#### Create Type (Admin)
```
POST /api/admin/types
```

**Body:**
```json
{
  "name": "Type Name"
}
```

#### Update Type (Admin)
```
PUT /api/admin/types/{id}
```

#### Delete Type (Admin)
```
DELETE /api/admin/types/{id}
```

### Catalogs

#### List Catalogs
```
GET /api/catalogs
```

#### Get Single Catalog
```
GET /api/catalogs/{id}
```

#### Get Catalog Products
```
GET /api/catalogs/{id}/products
```

#### Create Catalog (Admin)
```
POST /api/admin/catalogs
```

**Body:**
```json
{
  "name": "Catalog Name"
}
```

#### Update Catalog (Admin)
```
PUT /api/admin/catalogs/{id}
```

#### Delete Catalog (Admin)
```
DELETE /api/admin/catalogs/{id}
```

### VAT Rates

#### List VAT Rates
```
GET /api/vat
```

#### Get Single VAT Rate
```
GET /api/vat/{id}
```

#### Create VAT Rate (Admin)
```
POST /api/admin/vat
```

**Body:**
```json
{
  "name": "VAT 20%",
  "value_": 20.0
}
```

#### Update VAT Rate (Admin)
```
PUT /api/admin/vat/{id}
```

#### Delete VAT Rate (Admin)
```
DELETE /api/admin/vat/{id}
```

## Error Responses

### Validation Errors (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### Not Found (404)
```json
{
  "error": "Resource not found"
}
```

### Unauthorized (401)
```json
{
  "error": "Unauthenticated"
}
```

### Bad Request (400)
```json
{
  "error": "Error description"
}
```

## Sample Data

The service comes with sample data including:

**Brands:** Apple, Samsung, Sony, LG, Microsoft, Google, Amazon, Dell, HP, Lenovo

**Types:** Smartphone, Laptop, Tablet, Desktop, Monitor, Keyboard, Mouse, Headphones, Speaker, Camera

**Categories:** Electronics, Computers, Mobile Devices, Audio & Video, Gaming, Accessories, Networking, Storage, Monitors & Displays, Input Devices

**Catalogs:** Main Catalog, Premium Products, Budget Collection, New Arrivals, Clearance, Professional, Consumer, Enterprise

**VAT Rates:** 0%, 5.5%, 10%, 20%

**Sample Products:**
- iPhone 15 Pro (Apple, Smartphone)
- Samsung Galaxy S24 (Samsung, Smartphone)
- MacBook Pro 14" (Apple, Laptop)

## Development Commands

### Database Operations
```bash
# Run migrations
docker-compose exec products-service php artisan migrate

# Run seeders
docker-compose exec products-service php artisan db:seed

# Fresh database with seeders
docker-compose exec products-service php artisan migrate:fresh --seed
```

### Testing
```bash
# Run all tests
docker-compose exec products-service php artisan test

# Run specific test
docker-compose exec products-service php artisan test --filter ProductTest
```

### Code Quality
```bash
# Format code with Laravel Pint
docker-compose exec products-service ./vendor/bin/pint
```