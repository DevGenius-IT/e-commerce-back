# 🛠️ Guide Complet d'Implémentation d'un Service

## 🎯 Vue d'Ensemble

Ce guide détaille l'implémentation complète d'un nouveau microservice dans l'architecture e-commerce. Chaque service suit une structure standardisée avec communication asynchrone via RabbitMQ et intégration MinIO optionnelle.

## 📋 Prérequis

### 🔧 Outils Requis
- Docker & Docker Compose
- PHP 8.3+ avec extensions
- Composer 2.x
- Node.js (pour les outils de build)
- Git

### 🛠️ Extensions PHP Obligatoires
```dockerfile
# Extensions critiques pour RabbitMQ et MinIO
RUN docker-php-ext-install \
    pdo_mysql mbstring exif pcntl bcmath gd sockets
```

### 🏗️ Architecture Cible
```
Client → Nginx → API Gateway → RabbitMQ → Nouveau Service
                                    ↓
                            MinIO Storage (optionnel)
```

---

## 🚀 Étape 1: Création de la Structure

### 📁 Structure de Répertoire
```
services/your-service/
├── app/
│   ├── Console/Commands/       # Commandes Artisan
│   ├── Http/Controllers/API/   # Controllers API
│   ├── Models/                 # Models Eloquent
│   ├── Services/              # Services métier
│   └── Middleware/            # Middleware personnalisés
├── database/
│   ├── migrations/            # Migrations base de données
│   ├── seeders/              # Seeders de test
│   └── factories/            # Factories pour tests
├── routes/
│   ├── api.php               # Routes API
│   └── web.php               # Routes web (health checks)
├── tests/
│   ├── Feature/              # Tests fonctionnels
│   └── Unit/                 # Tests unitaires
├── config/                   # Configuration Laravel
├── storage/                  # Stockage temporaire
├── .env                      # Variables d'environnement
├── composer.json             # Dépendances PHP
├── Dockerfile               # Container Docker
└── README.md                # Documentation service
```

### 🎯 Exemple: Service Inventory

Créons un service de gestion d'inventaire comme exemple complet.

---

## 🚀 Étape 2: Configuration Docker

### 📦 Dockerfile
```dockerfile
# services/inventory-service/Dockerfile

FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libxml2-dev zip unzip netcat-traditional \
    libzip-dev libfreetype6-dev libjpeg62-turbo-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (CRITICAL: include sockets for RabbitMQ)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql mbstring exif pcntl bcmath gd sockets zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/inventory-service

# Copy application files
COPY services/inventory-service/ .
COPY shared/ ../../shared/

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www/inventory-service \
    && chmod -R 755 /var/www/inventory-service/storage \
    && chmod -R 755 /var/www/inventory-service/bootstrap/cache

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8013/health || exit 1

EXPOSE 8013

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8013"]
```

### 🐳 Docker Compose Integration
```yaml
# docker-compose.yml - Section à ajouter

services:
  inventory-service:
    build:
      context: .
      dockerfile: ./services/inventory-service/Dockerfile
    container_name: inventory-service
    volumes:
      - ./services/inventory-service:/var/www/inventory-service
      - ./shared:/var/www/shared
      - /var/www/inventory-service/vendor
    environment:
      - APP_ENV=local
      - CONTAINER_ROLE=app
      - DB_HOST=${DB_INVENTORY_HOST}
      - RABBITMQ_HOST=rabbitmq
      - MINIO_ENDPOINT=http://minio:9000
    ports:
      - "8013:8013"
    networks:
      - microservices-network
    depends_on:
      - inventory-db
      - rabbitmq
      - minio
    restart: unless-stopped

  inventory-db:
    image: mysql:8.0
    container_name: inventory-db
    environment:
      MYSQL_DATABASE: inventory_service_db
      MYSQL_ROOT_PASSWORD: root
      MYSQL_USER: inventory_user
      MYSQL_PASSWORD: inventory_pass
    ports:
      - "3319:3306"
    volumes:
      - inventory-db-data:/var/lib/mysql
    networks:
      - microservices-network
    restart: unless-stopped

volumes:
  inventory-db-data:
    driver: local
```

---

## 🚀 Étape 3: Configuration Laravel

### 📝 composer.json
```json
{
    "name": "e-commerce/inventory-service",
    "description": "Inventory Management Microservice",
    "keywords": ["laravel", "microservice", "inventory", "e-commerce"],
    "type": "project",
    "require": {
        "php": "^8.3",
        "e-commerce/shared": "@dev",
        "laravel/framework": "^11.0",
        "laravel/tinker": "^2.8",
        "php-amqplib/php-amqplib": "^3.5",
        "aws/aws-sdk-php": "^3.0",
        "tymon/jwt-auth": "^2.0"
    },
    "require-dev": {
        "fakerphp/faker": "^1.9.1",
        "laravel/pint": "^1.0",
        "laravel/sail": "^1.18",
        "mockery/mockery": "^1.4.4",
        "nunomaduro/collision": "^7.0",
        "phpunit/phpunit": "^10.1",
        "spatie/laravel-ignition": "^2.0"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../../shared",
            "options": {
                "symlink": true
            }
        }
    ],
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ],
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi"
        ]
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

### 🔧 Configuration .env
```env
# services/inventory-service/.env

APP_NAME="Inventory Service"
APP_ENV=local
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_URL=http://localhost:8013

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=${DB_INVENTORY_HOST:-inventory-db}
DB_PORT=3306
DB_DATABASE=inventory_service_db
DB_USERNAME=root
DB_PASSWORD=root

# RabbitMQ Configuration
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=microservices_exchange
RABBITMQ_QUEUE=inventory.requests

# MinIO Configuration (for file storage)
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=admin
MINIO_SECRET_KEY=adminpass123
MINIO_BUCKET=inventory
MINIO_REGION=us-east-1

# JWT Configuration (Shared)
JWT_SECRET=${JWT_SECRET}
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256

# Service-specific settings
INVENTORY_AUTO_REORDER=true
INVENTORY_LOW_STOCK_THRESHOLD=10
INVENTORY_NOTIFICATION_EMAIL=inventory@yourdomain.com

# Cache
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

# External APIs
SUPPLIER_API_BASE_URL=https://api.supplier.com
SUPPLIER_API_KEY=${SUPPLIER_API_KEY}
```

---

## 🚀 Étape 4: Models et Migrations

### 📊 Model Principal - Inventory
```php
<?php
// services/inventory-service/app/Models/Inventory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'sku',
        'quantity_available',
        'quantity_reserved',
        'quantity_incoming',
        'reorder_level',
        'reorder_quantity',
        'unit_cost',
        'last_restocked_at',
        'is_active'
    ];

    protected $casts = [
        'quantity_available' => 'integer',
        'quantity_reserved' => 'integer',
        'quantity_incoming' => 'integer',
        'reorder_level' => 'integer',
        'reorder_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'last_restocked_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    protected $dates = [
        'last_restocked_at',
        'deleted_at'
    ];

    /**
     * Relations
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity_available', '<=', 'reorder_level');
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Accessors & Mutators
     */
    public function getQuantityTotalAttribute(): int
    {
        return $this->quantity_available + $this->quantity_reserved + $this->quantity_incoming;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity_available <= $this->reorder_level;
    }

    public function getStockValueAttribute(): float
    {
        return $this->quantity_available * $this->unit_cost;
    }

    /**
     * Business Logic Methods
     */
    public function reserve(int $quantity, string $reason = null): bool
    {
        if ($this->quantity_available < $quantity) {
            return false;
        }

        $this->quantity_available -= $quantity;
        $this->quantity_reserved += $quantity;
        $this->save();

        // Create reservation record
        $this->reservations()->create([
            'quantity' => $quantity,
            'reason' => $reason,
            'created_by' => auth()->id(),
            'expires_at' => now()->addHours(24)
        ]);

        // Log movement
        $this->logMovement('RESERVED', -$quantity, $reason);

        return true;
    }

    public function release(int $quantity, string $reason = null): bool
    {
        if ($this->quantity_reserved < $quantity) {
            return false;
        }

        $this->quantity_available += $quantity;
        $this->quantity_reserved -= $quantity;
        $this->save();

        // Log movement
        $this->logMovement('RELEASED', $quantity, $reason);

        return true;
    }

    public function adjust(int $newQuantity, string $reason): void
    {
        $difference = $newQuantity - $this->quantity_available;
        $this->quantity_available = $newQuantity;
        $this->save();

        // Log movement
        $movementType = $difference > 0 ? 'ADJUSTMENT_IN' : 'ADJUSTMENT_OUT';
        $this->logMovement($movementType, $difference, $reason);
    }

    public function restock(int $quantity, float $unitCost = null, string $reference = null): void
    {
        $this->quantity_available += $quantity;
        
        if ($unitCost !== null) {
            $this->unit_cost = $unitCost;
        }
        
        $this->last_restocked_at = now();
        $this->save();

        // Log movement
        $this->logMovement('RESTOCK', $quantity, "Restock - Ref: {$reference}");
    }

    private function logMovement(string $type, int $quantity, string $reason = null): void
    {
        $this->movements()->create([
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $this->getOriginal('quantity_available'),
            'quantity_after' => $this->quantity_available,
            'reason' => $reason,
            'created_by' => auth()->id() ?? 'system',
            'reference' => uniqid('MOV_')
        ]);
    }
}
```

### 📊 Model Mouvement d'Inventaire
```php
<?php
// services/inventory-service/app/Models/InventoryMovement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'inventory_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reason',
        'reference',
        'created_by'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer'
    ];

    const TYPES = [
        'RESTOCK' => 'Restock',
        'SALE' => 'Sale',
        'RESERVED' => 'Reserved',
        'RELEASED' => 'Released',
        'ADJUSTMENT_IN' => 'Adjustment In',
        'ADJUSTMENT_OUT' => 'Adjustment Out',
        'TRANSFER_IN' => 'Transfer In',
        'TRANSFER_OUT' => 'Transfer Out',
        'DAMAGED' => 'Damaged',
        'LOST' => 'Lost'
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
```

### 🏭 Model Warehouse
```php
<?php
// services/inventory-service/app/Models/Warehouse.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'country',
        'manager_email',
        'phone',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

### 📋 Migrations
```php
<?php
// services/inventory-service/database/migrations/2025_01_15_000001_create_warehouses_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address');
            $table->string('city');
            $table->string('country');
            $table->string('manager_email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active']);
            $table->index(['code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouses');
    }
};
```

```php
<?php
// services/inventory-service/database/migrations/2025_01_15_000002_create_inventories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->index(); // Reference to products-service
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->string('sku')->unique();
            $table->integer('quantity_available')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_incoming')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->integer('reorder_quantity')->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->timestamp('last_restocked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['sku']);
            $table->index(['is_active']);
            $table->index(['quantity_available']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventories');
    }
};
```

```php
<?php
// services/inventory-service/database/migrations/2025_01_15_000003_create_inventory_movements_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->onDelete('cascade');
            $table->string('type'); // RESTOCK, SALE, RESERVED, etc.
            $table->integer('quantity'); // Can be negative
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->text('reason')->nullable();
            $table->string('reference')->nullable();
            $table->string('created_by')->default('system');
            $table->timestamps();
            
            $table->index(['inventory_id', 'created_at']);
            $table->index(['type']);
            $table->index(['reference']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_movements');
    }
};
```

---

## 🚀 Étape 5: Controllers API

### 🎯 Controller Principal - InventoryController
```php
<?php
// services/inventory-service/app/Http/Controllers/API/InventoryController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    private InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'service' => 'inventory-service',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
            'database' => $this->checkDatabaseConnection(),
            'rabbitmq' => $this->checkRabbitMQConnection()
        ]);
    }

    /**
     * List inventory items with filters
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'integer|exists:warehouses,id',
            'product_id' => 'integer',
            'sku' => 'string',
            'low_stock' => 'boolean',
            'per_page' => 'integer|min:1|max:100'
        ]);

        $query = Inventory::with(['warehouse'])
            ->active();

        // Apply filters
        if ($request->warehouse_id) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->sku) {
            $query->where('sku', 'like', "%{$request->sku}%");
        }

        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        $inventories = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $inventories->items(),
            'meta' => [
                'current_page' => $inventories->currentPage(),
                'total' => $inventories->total(),
                'per_page' => $inventories->perPage(),
                'last_page' => $inventories->lastPage()
            ]
        ]);
    }

    /**
     * Get specific inventory item
     */
    public function show(int $id): JsonResponse
    {
        $inventory = Inventory::with(['warehouse', 'movements' => function($query) {
            $query->latest()->limit(10);
        }])->findOrFail($id);

        return response()->json([
            'data' => $inventory,
            'recent_movements' => $inventory->movements,
            'stock_info' => [
                'total_value' => $inventory->stock_value,
                'is_low_stock' => $inventory->is_low_stock,
                'quantity_total' => $inventory->quantity_total
            ]
        ]);
    }

    /**
     * Create new inventory item
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'sku' => 'required|string|unique:inventories,sku',
            'quantity_available' => 'required|integer|min:0',
            'reorder_level' => 'integer|min:0',
            'reorder_quantity' => 'integer|min:1',
            'unit_cost' => 'numeric|min:0'
        ]);

        try {
            $inventory = $this->inventoryService->createInventoryItem($request->all());

            return response()->json([
                'success' => true,
                'data' => $inventory,
                'message' => 'Inventory item created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create inventory item',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update inventory item
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $inventory = Inventory::findOrFail($id);

        $request->validate([
            'reorder_level' => 'integer|min:0',
            'reorder_quantity' => 'integer|min:1',
            'unit_cost' => 'numeric|min:0',
            'is_active' => 'boolean'
        ]);

        try {
            $inventory->update($request->only([
                'reorder_level',
                'reorder_quantity', 
                'unit_cost',
                'is_active'
            ]));

            return response()->json([
                'success' => true,
                'data' => $inventory->fresh(),
                'message' => 'Inventory item updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update inventory item',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reserve inventory
     */
    public function reserve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'string|max:255'
        ]);

        $inventory = Inventory::findOrFail($id);

        try {
            $success = $this->inventoryService->reserveInventory(
                $inventory,
                $request->quantity,
                $request->reason
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => $inventory->fresh(),
                    'message' => 'Inventory reserved successfully'
                ]);
            } else {
                return response()->json([
                    'error' => 'Insufficient stock',
                    'available_quantity' => $inventory->quantity_available
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to reserve inventory',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Release reserved inventory
     */
    public function release(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'string|max:255'
        ]);

        $inventory = Inventory::findOrFail($id);

        try {
            $success = $inventory->release($request->quantity, $request->reason);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => $inventory->fresh(),
                    'message' => 'Inventory released successfully'
                ]);
            } else {
                return response()->json([
                    'error' => 'Insufficient reserved quantity',
                    'reserved_quantity' => $inventory->quantity_reserved
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to release inventory',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adjust inventory quantity
     */
    public function adjust(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'new_quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:255'
        ]);

        $inventory = Inventory::findOrFail($id);

        try {
            $this->inventoryService->adjustInventory(
                $inventory,
                $request->new_quantity,
                $request->reason
            );

            return response()->json([
                'success' => true,
                'data' => $inventory->fresh(),
                'message' => 'Inventory adjusted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to adjust inventory',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restock inventory
     */
    public function restock(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'numeric|min:0',
            'reference' => 'string|max:100'
        ]);

        $inventory = Inventory::findOrFail($id);

        try {
            $this->inventoryService->restockInventory(
                $inventory,
                $request->quantity,
                $request->unit_cost,
                $request->reference
            );

            return response()->json([
                'success' => true,
                'data' => $inventory->fresh(),
                'message' => 'Inventory restocked successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to restock inventory',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory movements history
     */
    public function movements(Request $request, int $id): JsonResponse
    {
        $inventory = Inventory::findOrFail($id);

        $request->validate([
            'type' => Rule::in(array_keys(\App\Models\InventoryMovement::TYPES)),
            'per_page' => 'integer|min:1|max:100'
        ]);

        $query = $inventory->movements();

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $movements = $query->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $movements->items(),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'total' => $movements->total(),
                'per_page' => $movements->perPage(),
                'last_page' => $movements->lastPage()
            ]
        ]);
    }

    /**
     * Get low stock report
     */
    public function lowStockReport(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'integer|exists:warehouses,id'
        ]);

        $query = Inventory::with(['warehouse'])
            ->active()
            ->lowStock();

        if ($request->warehouse_id) {
            $query->byWarehouse($request->warehouse_id);
        }

        $lowStockItems = $query->get();

        return response()->json([
            'data' => $lowStockItems,
            'summary' => [
                'total_items' => $lowStockItems->count(),
                'total_value_at_risk' => $lowStockItems->sum('stock_value'),
                'by_warehouse' => $lowStockItems->groupBy('warehouse.name')
                    ->map(function ($items, $warehouse) {
                        return [
                            'warehouse' => $warehouse,
                            'count' => $items->count(),
                            'value' => $items->sum('stock_value')
                        ];
                    })->values()
            ]
        ]);
    }

    /**
     * Private helper methods
     */
    private function checkDatabaseConnection(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkRabbitMQConnection(): bool
    {
        try {
            $rabbitMQClient = new \Shared\Services\RabbitMQClientService();
            $rabbitMQClient->connect();
            return $rabbitMQClient->isConnected();
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

---

## 🚀 Étape 6: Services Métier

### 🎯 Service Principal - InventoryService
```php
<?php
// services/inventory-service/app/Services/InventoryService.php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shared\Services\RabbitMQClientService;

class InventoryService
{
    private RabbitMQClientService $rabbitMQClient;

    public function __construct()
    {
        $this->rabbitMQClient = new RabbitMQClientService();
    }

    /**
     * Create new inventory item
     */
    public function createInventoryItem(array $data): Inventory
    {
        return DB::transaction(function () use ($data) {
            // Validate warehouse exists and is active
            $warehouse = Warehouse::active()->findOrFail($data['warehouse_id']);

            // Create inventory item
            $inventory = Inventory::create([
                'product_id' => $data['product_id'],
                'warehouse_id' => $warehouse->id,
                'sku' => $data['sku'],
                'quantity_available' => $data['quantity_available'],
                'reorder_level' => $data['reorder_level'] ?? 0,
                'reorder_quantity' => $data['reorder_quantity'] ?? 10,
                'unit_cost' => $data['unit_cost'] ?? 0,
                'last_restocked_at' => now(),
                'is_active' => true
            ]);

            // Log initial stock if > 0
            if ($data['quantity_available'] > 0) {
                $inventory->logMovement('INITIAL_STOCK', $data['quantity_available'], 'Initial inventory creation');
            }

            // Notify other services about new inventory
            $this->notifyInventoryCreated($inventory);

            return $inventory;
        });
    }

    /**
     * Reserve inventory with validation
     */
    public function reserveInventory(Inventory $inventory, int $quantity, string $reason = null): bool
    {
        return DB::transaction(function () use ($inventory, $quantity, $reason) {
            // Lock the record for update
            $inventory = Inventory::lockForUpdate()->find($inventory->id);

            if ($inventory->quantity_available < $quantity) {
                Log::warning('Insufficient stock for reservation', [
                    'inventory_id' => $inventory->id,
                    'requested' => $quantity,
                    'available' => $inventory->quantity_available
                ]);
                return false;
            }

            $success = $inventory->reserve($quantity, $reason);

            if ($success) {
                // Check if stock is now low and send notification
                if ($inventory->is_low_stock) {
                    $this->sendLowStockNotification($inventory);
                }

                // Notify other services about reservation
                $this->notifyInventoryReserved($inventory, $quantity, $reason);
            }

            return $success;
        });
    }

    /**
     * Adjust inventory with comprehensive logging
     */
    public function adjustInventory(Inventory $inventory, int $newQuantity, string $reason): void
    {
        DB::transaction(function () use ($inventory, $newQuantity, $reason) {
            $oldQuantity = $inventory->quantity_available;
            $difference = $newQuantity - $oldQuantity;

            $inventory->adjust($newQuantity, $reason);

            Log::info('Inventory adjusted', [
                'inventory_id' => $inventory->id,
                'sku' => $inventory->sku,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'difference' => $difference,
                'reason' => $reason,
                'user_id' => auth()->id()
            ]);

            // Notify other services about adjustment
            $this->notifyInventoryAdjusted($inventory, $difference, $reason);
        });
    }

    /**
     * Restock inventory with supplier integration
     */
    public function restockInventory(Inventory $inventory, int $quantity, float $unitCost = null, string $reference = null): void
    {
        DB::transaction(function () use ($inventory, $quantity, $unitCost, $reference) {
            $oldQuantity = $inventory->quantity_available;
            
            $inventory->restock($quantity, $unitCost, $reference);

            Log::info('Inventory restocked', [
                'inventory_id' => $inventory->id,
                'sku' => $inventory->sku,
                'quantity_added' => $quantity,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $inventory->quantity_available,
                'unit_cost' => $unitCost,
                'reference' => $reference
            ]);

            // Update incoming quantity if this was expected
            if ($inventory->quantity_incoming > 0) {
                $expectedQuantity = min($quantity, $inventory->quantity_incoming);
                $inventory->quantity_incoming -= $expectedQuantity;
                $inventory->save();
            }

            // Notify other services about restock
            $this->notifyInventoryRestocked($inventory, $quantity, $reference);
        });
    }

    /**
     * Auto-reorder low stock items
     */
    public function processAutoReorders(): array
    {
        if (!config('app.inventory_auto_reorder', false)) {
            return ['message' => 'Auto-reorder is disabled'];
        }

        $lowStockItems = Inventory::active()
            ->lowStock()
            ->where('reorder_quantity', '>', 0)
            ->get();

        $reordersCreated = [];

        foreach ($lowStockItems as $inventory) {
            try {
                $reorder = $this->createReorderRequest($inventory);
                $reordersCreated[] = $reorder;

                // Update incoming quantity
                $inventory->quantity_incoming += $inventory->reorder_quantity;
                $inventory->save();

            } catch (\Exception $e) {
                Log::error('Failed to create reorder request', [
                    'inventory_id' => $inventory->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'reorders_created' => count($reordersCreated),
            'details' => $reordersCreated
        ];
    }

    /**
     * Generate inventory valuation report
     */
    public function generateValuationReport(int $warehouseId = null): array
    {
        $query = Inventory::active();

        if ($warehouseId) {
            $query->byWarehouse($warehouseId);
        }

        $inventories = $query->with('warehouse')->get();

        $totalValue = $inventories->sum('stock_value');
        $totalQuantity = $inventories->sum('quantity_available');
        $lowStockCount = $inventories->filter->is_low_stock->count();

        $byWarehouse = $inventories->groupBy('warehouse.name')
            ->map(function ($items, $warehouse) {
                return [
                    'warehouse' => $warehouse,
                    'total_items' => $items->count(),
                    'total_quantity' => $items->sum('quantity_available'),
                    'total_value' => $items->sum('stock_value'),
                    'low_stock_items' => $items->filter->is_low_stock->count()
                ];
            })->values();

        return [
            'summary' => [
                'total_value' => round($totalValue, 2),
                'total_quantity' => $totalQuantity,
                'total_items' => $inventories->count(),
                'low_stock_items' => $lowStockCount,
                'low_stock_percentage' => $inventories->count() > 0 ? round(($lowStockCount / $inventories->count()) * 100, 2) : 0
            ],
            'by_warehouse' => $byWarehouse,
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * Private notification methods
     */
    private function notifyInventoryCreated(Inventory $inventory): void
    {
        try {
            $this->rabbitMQClient->publish('inventory.events', [
                'event' => 'inventory.created',
                'data' => [
                    'inventory_id' => $inventory->id,
                    'product_id' => $inventory->product_id,
                    'sku' => $inventory->sku,
                    'quantity' => $inventory->quantity_available,
                    'warehouse_id' => $inventory->warehouse_id
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish inventory created event', ['error' => $e->getMessage()]);
        }
    }

    private function notifyInventoryReserved(Inventory $inventory, int $quantity, string $reason = null): void
    {
        try {
            $this->rabbitMQClient->publish('inventory.events', [
                'event' => 'inventory.reserved',
                'data' => [
                    'inventory_id' => $inventory->id,
                    'sku' => $inventory->sku,
                    'quantity_reserved' => $quantity,
                    'quantity_available' => $inventory->quantity_available,
                    'reason' => $reason
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish inventory reserved event', ['error' => $e->getMessage()]);
        }
    }

    private function notifyInventoryAdjusted(Inventory $inventory, int $difference, string $reason): void
    {
        try {
            $this->rabbitMQClient->publish('inventory.events', [
                'event' => 'inventory.adjusted',
                'data' => [
                    'inventory_id' => $inventory->id,
                    'sku' => $inventory->sku,
                    'quantity_difference' => $difference,
                    'new_quantity' => $inventory->quantity_available,
                    'reason' => $reason
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish inventory adjusted event', ['error' => $e->getMessage()]);
        }
    }

    private function notifyInventoryRestocked(Inventory $inventory, int $quantity, string $reference = null): void
    {
        try {
            $this->rabbitMQClient->publish('inventory.events', [
                'event' => 'inventory.restocked',
                'data' => [
                    'inventory_id' => $inventory->id,
                    'sku' => $inventory->sku,
                    'quantity_added' => $quantity,
                    'new_quantity' => $inventory->quantity_available,
                    'reference' => $reference
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish inventory restocked event', ['error' => $e->getMessage()]);
        }
    }

    private function sendLowStockNotification(Inventory $inventory): void
    {
        try {
            $this->rabbitMQClient->publish('notifications.events', [
                'event' => 'low_stock_alert',
                'data' => [
                    'inventory_id' => $inventory->id,
                    'sku' => $inventory->sku,
                    'current_quantity' => $inventory->quantity_available,
                    'reorder_level' => $inventory->reorder_level,
                    'warehouse' => $inventory->warehouse->name
                ],
                'recipients' => [config('app.inventory_notification_email')],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send low stock notification', ['error' => $e->getMessage()]);
        }
    }

    private function createReorderRequest(Inventory $inventory): array
    {
        // Here you would integrate with supplier API or create internal purchase order
        return [
            'inventory_id' => $inventory->id,
            'sku' => $inventory->sku,
            'quantity_to_order' => $inventory->reorder_quantity,
            'estimated_cost' => $inventory->reorder_quantity * $inventory->unit_cost,
            'status' => 'pending',
            'created_at' => now()->toISOString()
        ];
    }
}
```

---

## 🚀 Étape 7: RabbitMQ Integration

### 🐰 Listener RabbitMQ
```php
<?php
// services/inventory-service/app/Console/Commands/ListenRabbitMQRequestsCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Shared\Services\RabbitMQRequestHandlerService;
use Exception;

class ListenRabbitMQRequestsCommand extends Command
{
    protected $signature = 'rabbitmq:listen-requests {--timeout=0 : Maximum execution time in seconds (0 = no timeout)}';
    protected $description = 'Listen for incoming RabbitMQ requests and process them';
    protected RabbitMQRequestHandlerService $requestHandler;

    public function handle()
    {
        $this->info('Starting RabbitMQ Request Listener for inventory-service...');

        try {
            // Initialize the request handler for inventory service
            $serviceUrl = env('APP_URL', 'http://localhost:8013');
            $this->requestHandler = new RabbitMQRequestHandlerService('inventory', $serviceUrl);

            // Set up graceful shutdown
            $this->setupSignalHandlers();

            // Connect to RabbitMQ
            $this->requestHandler->connect();
            $this->info('Connected to RabbitMQ successfully');

            $this->info('Listening for requests on queue: inventory.requests');
            $this->info('Press Ctrl+C to stop...');

            // Start listening for requests
            $this->requestHandler->startListening();

        } catch (Exception $e) {
            $this->error('Failed to start RabbitMQ listener: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info('RabbitMQ Request Listener stopped');
        return Command::SUCCESS;
    }

    protected function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
            pcntl_signal(SIGQUIT, [$this, 'handleShutdown']);
        }
    }

    public function handleShutdown(): void
    {
        $this->info('Received shutdown signal, stopping gracefully...');
        if ($this->requestHandler) {
            $this->requestHandler->stopListening();
            $this->requestHandler->disconnect();
        }
    }
}
```

### 📡 Event Listener pour les Commandes
```php
<?php
// services/inventory-service/app/Console/Commands/ListenOrderEventsCommand.php

namespace App\Console\Commands;

use App\Services\InventoryService;
use Illuminate\Console\Command;
use Shared\Services\RabbitMQClientService;

class ListenOrderEventsCommand extends Command
{
    protected $signature = 'rabbitmq:listen-order-events';
    protected $description = 'Listen for order events to update inventory';

    private RabbitMQClientService $rabbitMQClient;
    private InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        parent::__construct();
        $this->inventoryService = $inventoryService;
        $this->rabbitMQClient = new RabbitMQClientService();
    }

    public function handle()
    {
        $this->info('Starting Order Events Listener for inventory updates...');

        try {
            $this->rabbitMQClient->connect();
            
            // Listen for order events
            $this->rabbitMQClient->consume('orders.events', function ($message) {
                $this->processOrderEvent(json_decode($message->body, true));
            });

        } catch (\Exception $e) {
            $this->error('Failed to listen for order events: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function processOrderEvent(array $eventData): void
    {
        try {
            $event = $eventData['event'] ?? '';
            $data = $eventData['data'] ?? [];

            switch ($event) {
                case 'order.confirmed':
                    $this->handleOrderConfirmed($data);
                    break;

                case 'order.cancelled':
                    $this->handleOrderCancelled($data);
                    break;

                case 'order.shipped':
                    $this->handleOrderShipped($data);
                    break;

                default:
                    $this->info("Unhandled order event: {$event}");
            }

        } catch (\Exception $e) {
            $this->error('Failed to process order event: ' . $e->getMessage());
        }
    }

    private function handleOrderConfirmed(array $data): void
    {
        $orderId = $data['order_id'] ?? null;
        $items = $data['items'] ?? [];

        foreach ($items as $item) {
            $inventory = \App\Models\Inventory::where('product_id', $item['product_id'])->first();
            
            if ($inventory) {
                $this->inventoryService->reserveInventory(
                    $inventory,
                    $item['quantity'],
                    "Order #{$orderId} confirmed"
                );
                
                $this->info("Reserved {$item['quantity']} units of SKU {$inventory->sku} for order #{$orderId}");
            }
        }
    }

    private function handleOrderCancelled(array $data): void
    {
        $orderId = $data['order_id'] ?? null;
        $items = $data['items'] ?? [];

        foreach ($items as $item) {
            $inventory = \App\Models\Inventory::where('product_id', $item['product_id'])->first();
            
            if ($inventory) {
                $inventory->release($item['quantity'], "Order #{$orderId} cancelled");
                $this->info("Released {$item['quantity']} units of SKU {$inventory->sku} from cancelled order #{$orderId}");
            }
        }
    }

    private function handleOrderShipped(array $data): void
    {
        $orderId = $data['order_id'] ?? null;
        $items = $data['items'] ?? [];

        foreach ($items as $item) {
            $inventory = \App\Models\Inventory::where('product_id', $item['product_id'])->first();
            
            if ($inventory) {
                // Move from reserved to actually consumed
                $inventory->quantity_reserved -= $item['quantity'];
                $inventory->save();
                
                $inventory->logMovement('SALE', -$item['quantity'], "Order #{$orderId} shipped");
                $this->info("Consumed {$item['quantity']} units of SKU {$inventory->sku} for shipped order #{$orderId}");
            }
        }
    }
}
```

---

## 🚀 Étape 8: Routes et Middleware

### 🛣️ Routes API
```php
<?php
// services/inventory-service/routes/api.php

use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\WarehouseController;
use Illuminate\Support\Facades\Route;

// Health check endpoint (public)
Route::get('health', [InventoryController::class, 'health']);

// Public routes (accessible via API Gateway)
Route::group([], function () {
    // Inventory read-only endpoints for products service
    Route::get('inventory/product/{productId}', [InventoryController::class, 'getByProduct']);
    Route::get('inventory/{id}/availability', [InventoryController::class, 'checkAvailability']);
    
    // Warehouse information (public)
    Route::get('warehouses', [WarehouseController::class, 'index']);
    Route::get('warehouses/{id}', [WarehouseController::class, 'show']);
});

// Protected routes (require JWT authentication)
Route::middleware('auth:jwt')->group(function () {
    // Inventory management
    Route::apiResource('inventory', InventoryController::class);
    
    // Inventory operations
    Route::post('inventory/{id}/reserve', [InventoryController::class, 'reserve']);
    Route::post('inventory/{id}/release', [InventoryController::class, 'release']);
    Route::post('inventory/{id}/adjust', [InventoryController::class, 'adjust']);
    Route::post('inventory/{id}/restock', [InventoryController::class, 'restock']);
    
    // Inventory reports
    Route::get('inventory/{id}/movements', [InventoryController::class, 'movements']);
    Route::get('reports/low-stock', [InventoryController::class, 'lowStockReport']);
    Route::get('reports/valuation', [InventoryController::class, 'valuationReport']);
    
    // Warehouse management (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('warehouses', WarehouseController::class)->except(['index', 'show']);
    });
});

// Internal routes for service-to-service communication
Route::prefix('internal')->group(function () {
    Route::post('inventory/bulk-reserve', [InventoryController::class, 'bulkReserve']);
    Route::post('inventory/bulk-release', [InventoryController::class, 'bulkRelease']);
    Route::get('inventory/low-stock-alerts', [InventoryController::class, 'getLowStockAlerts']);
});
```

### 🔒 Middleware JWT Custom
```php
<?php
// services/inventory-service/app/Http/Middleware/InventoryAccessMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InventoryAccessMiddleware
{
    public function handle(Request $request, Closure $next, string $permission = null)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check specific inventory permissions
        if ($permission) {
            switch ($permission) {
                case 'manage_inventory':
                    if (!$user->hasRole(['admin', 'inventory_manager'])) {
                        return response()->json(['error' => 'Insufficient permissions'], 403);
                    }
                    break;

                case 'view_reports':
                    if (!$user->hasRole(['admin', 'inventory_manager', 'inventory_viewer'])) {
                        return response()->json(['error' => 'Insufficient permissions'], 403);
                    }
                    break;

                case 'adjust_inventory':
                    if (!$user->hasRole(['admin', 'inventory_manager'])) {
                        return response()->json(['error' => 'Insufficient permissions'], 403);
                    }
                    break;
            }
        }

        return $next($request);
    }
}
```

---

## 🚀 Étape 9: Tests

### 🧪 Tests Fonctionnels
```php
<?php
// services/inventory-service/tests/Feature/InventoryManagementTest.php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test warehouse
        $this->warehouse = Warehouse::factory()->create();
        
        // Create test inventory
        $this->inventory = Inventory::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'quantity_available' => 100,
            'reorder_level' => 10
        ]);
    }

    public function test_can_get_inventory_list()
    {
        $response = $this->getJson('/api/inventory');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id', 'sku', 'quantity_available', 'warehouse'
                        ]
                    ],
                    'meta'
                ]);
    }

    public function test_can_reserve_inventory()
    {
        $response = $this->postJson("/api/inventory/{$this->inventory->id}/reserve", [
            'quantity' => 50,
            'reason' => 'Test reservation'
        ]);

        $response->assertStatus(200);
        
        $this->inventory->refresh();
        $this->assertEquals(50, $this->inventory->quantity_available);
        $this->assertEquals(50, $this->inventory->quantity_reserved);
    }

    public function test_cannot_reserve_more_than_available()
    {
        $response = $this->postJson("/api/inventory/{$this->inventory->id}/reserve", [
            'quantity' => 150,
            'reason' => 'Test over-reservation'
        ]);

        $response->assertStatus(400)
                ->assertJson(['error' => 'Insufficient stock']);
    }

    public function test_can_adjust_inventory()
    {
        $response = $this->postJson("/api/inventory/{$this->inventory->id}/adjust", [
            'new_quantity' => 200,
            'reason' => 'Inventory count adjustment'
        ]);

        $response->assertStatus(200);
        
        $this->inventory->refresh();
        $this->assertEquals(200, $this->inventory->quantity_available);
    }

    public function test_low_stock_report()
    {
        // Create low stock item
        $lowStockInventory = Inventory::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'quantity_available' => 5,
            'reorder_level' => 10
        ]);

        $response = $this->getJson('/api/reports/low-stock');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'sku', 'quantity_available', 'reorder_level']
                    ],
                    'summary'
                ]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_inventory_movements_tracking()
    {
        // Reserve inventory to create movement
        $this->postJson("/api/inventory/{$this->inventory->id}/reserve", [
            'quantity' => 30,
            'reason' => 'Test movement'
        ]);

        $response = $this->getJson("/api/inventory/{$this->inventory->id}/movements");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['type', 'quantity', 'reason', 'created_at']
                    ]
                ]);
    }
}
```

### 🧪 Tests Unitaires
```php
<?php
// services/inventory-service/tests/Unit/InventoryModelTest.php

namespace Tests\Unit;

use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_can_be_reserved()
    {
        $inventory = Inventory::factory()->create([
            'quantity_available' => 100,
            'quantity_reserved' => 0
        ]);

        $result = $inventory->reserve(50, 'Test reservation');

        $this->assertTrue($result);
        $this->assertEquals(50, $inventory->quantity_available);
        $this->assertEquals(50, $inventory->quantity_reserved);
    }

    public function test_inventory_calculates_total_quantity()
    {
        $inventory = Inventory::factory()->create([
            'quantity_available' => 100,
            'quantity_reserved' => 30,
            'quantity_incoming' => 20
        ]);

        $this->assertEquals(150, $inventory->quantity_total);
    }

    public function test_inventory_detects_low_stock()
    {
        $inventory = Inventory::factory()->create([
            'quantity_available' => 5,
            'reorder_level' => 10
        ]);

        $this->assertTrue($inventory->is_low_stock);
    }

    public function test_inventory_calculates_stock_value()
    {
        $inventory = Inventory::factory()->create([
            'quantity_available' => 100,
            'unit_cost' => 10.50
        ]);

        $this->assertEquals(1050.00, $inventory->stock_value);
    }
}
```

---

## 🚀 Étape 10: Déploiement et Integration

### 🔧 Makefile Integration
```makefile
# Ajout au Makefile principal

# Inventory Service specific commands
inventory-migrate:
	@echo "$(YELLOW)🗄️ Running inventory service migrations...$(NC)"
	@docker-compose exec inventory-service php artisan migrate --force

inventory-seed:
	@echo "$(YELLOW)🌱 Seeding inventory service...$(NC)"
	@docker-compose exec inventory-service php artisan db:seed --force

inventory-test:
	@echo "$(YELLOW)🧪 Testing inventory service...$(NC)"
	@docker-compose exec inventory-service php artisan test

inventory-consumer:
	@echo "$(YELLOW)🐰 Starting inventory RabbitMQ consumer...$(NC)"
	@docker-compose exec -d inventory-service php artisan rabbitmq:listen-requests

inventory-events:
	@echo "$(YELLOW)📡 Starting inventory events listener...$(NC)"
	@docker-compose exec -d inventory-service php artisan rabbitmq:listen-order-events
```

### 🔧 API Gateway Integration
Ajouter dans `services/api-gateway/app/Services/GatewayRouterService.php` :

```php
protected array $serviceConfig = [
    // Services existants...
    
    'inventory' => [
        'queue' => 'inventory.requests',
        'timeout' => 5000,
        'healthEndpoint' => '/health',
        'port' => 8013,
        'version' => '1.0.0',
        'publicRoutes' => [
            'GET:inventory/product/*',
            'GET:inventory/*/availability',
            'GET:warehouses',
            'GET:warehouses/*'
        ],
        'protectedRoutes' => [
            'POST:inventory',
            'PUT:inventory/*',
            'DELETE:inventory/*',
            'POST:inventory/*/reserve',
            'POST:inventory/*/release',
            'POST:inventory/*/adjust',
            'POST:inventory/*/restock',
            'GET:reports/*'
        ]
    ]
];
```

### 📊 Tests d'Intégration
```bash
# Tests complets du service
make inventory-test

# Test de l'intégration API Gateway
curl http://localhost/api/v1/inventory/health

# Test réservation via API Gateway
curl -X POST http://localhost/api/v1/inventory/1/reserve \
  -H "Authorization: Bearer {jwt_token}" \
  -H "Content-Type: application/json" \
  -d '{"quantity": 10, "reason": "Test reservation"}'

# Test rapport stock faible
curl http://localhost/api/v1/reports/low-stock \
  -H "Authorization: Bearer {jwt_token}"
```

---

## 🚀 Étape 11: Documentation et Maintenance

### 📝 README du Service
```markdown
# Inventory Service

## Overview
Microservice de gestion d'inventaire pour la plateforme e-commerce.

## Features
- ✅ Gestion multi-entrepôts
- ✅ Réservations automatiques
- ✅ Suivi des mouvements
- ✅ Alertes stock faible
- ✅ Rapports de valorisation
- ✅ Integration RabbitMQ
- ✅ API REST complète

## Quick Start
```bash
# Démarrer le service
docker-compose up inventory-service

# Migrations
make inventory-migrate

# Tests
make inventory-test

# Consumer RabbitMQ
make inventory-consumer
```

## API Endpoints
- `GET /health` - Health check
- `GET /inventory` - Liste inventaire
- `POST /inventory/{id}/reserve` - Réserver stock
- `GET /reports/low-stock` - Rapport stock faible

## Environment Variables
- `INVENTORY_AUTO_REORDER` - Réapprovisionnement automatique
- `INVENTORY_LOW_STOCK_THRESHOLD` - Seuil stock faible
- `MINIO_BUCKET` - Bucket MinIO pour documents
```

### 🔧 Scripts de Maintenance
```bash
#!/bin/bash
# scripts/inventory-maintenance.sh

# Nettoyage mouvements anciens (> 1 an)
docker-compose exec inventory-service php artisan inventory:cleanup-old-movements

# Traitement réapprovisionnements automatiques
docker-compose exec inventory-service php artisan inventory:process-auto-reorders

# Génération rapport valorisation
docker-compose exec inventory-service php artisan inventory:generate-valuation-report

# Alerte stocks faibles
docker-compose exec inventory-service php artisan inventory:check-low-stock
```

<function_calls>
<invoke name="TodoWrite">
<parameter name="todos">[{"content": "Mettre \u00e0 jour NEXT.md avec l'int\u00e9gration Minio", "status": "completed", "activeForm": "Updating NEXT.md with Minio integration"}, {"content": "Organiser les fichiers de documentation", "status": "completed", "activeForm": "Organizing documentation files"}, {"content": "Cr\u00e9er un index de la documentation", "status": "completed", "activeForm": "Creating documentation index"}, {"content": "Cr\u00e9er une documentation compl\u00e8te d'impl\u00e9mentation de service", "status": "completed", "activeForm": "Creating complete service implementation documentation"}]