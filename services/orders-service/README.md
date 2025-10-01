# Baskets Service - API Documentation

## 📋 Overview

Le **baskets-service** gère les paniers d'achat et les codes promotionnels de la plateforme e-commerce. Il permet aux utilisateurs de créer et gérer leurs paniers, d'appliquer des codes promo, et aux administrateurs de gérer les codes promotionnels et leurs types.

## 🚀 Service Information

- **Port**: 8005
- **Base URL**: `http://localhost/baskets/`
- **Database**: `baskets_service_db` (port 3319)
- **Status**: ✅ **OPÉRATIONNEL**

## 🔐 Authentication

Utilise JWT authentication via le middleware partagé `\Shared\Middleware\JWTAuthMiddleware::class`

**Token de test valide:**
```
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vYXV0aC1zZXJ2aWNlOjgwMDEvYXBpL2xvZ2luIiwiaWF0IjoxNzU4Njk4MTM0LCJleHAiOjE3NTg3MDE3MzQsIm5iZiI6MTc1ODY5ODEzNCwianRpIjoiVXB6S3ExeUhmNmNCS0JlOSIsInN1YiI6IjEiLCJwcnYiOiJiNzc0MzY1ZWVlNjhkNTc4N2VlNDQwNDVmNzIzMzM3ODI5Mjk4Y2U3Iiwicm9sZSI6bnVsbCwiZW1haWwiOiJreWxpYW5AY29sbGVjdC12ZXJ5dGhpbmcuY29tIn0.eV9-h-XiHHXCnqCu3B9OU1y7Ef9nRLItlRfcOKZbHUs
```

**Headers requis:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

## 🗄️ Database Schema

### Tables

#### `baskets`
- `id` (PK)
- `amount` (decimal) - Montant total calculé
- `user_id` (FK) - Référence vers auth-service
- `created_at`, `updated_at`, `deleted_at`

#### `basket_items`
- `id` (PK)
- `basket_id` (FK)
- `product_id` (FK) - Référence vers products-service
- `quantity` (integer)
- `price_ht` (decimal)
- `created_at`, `updated_at`

#### `promo_codes`
- `id` (PK)
- `name` (string)
- `code` (string, unique)
- `discount` (decimal)
- `id_1` (FK vers types)
- `created_at`, `updated_at`, `deleted_at`

#### `types`
- `id` (PK)
- `name` (string) - Ex: "Pourcentage", "Montant fixe"
- `symbol` (string) - Ex: "%", "€"
- `created_at`, `updated_at`, `deleted_at`

#### `basket_promo_code` (pivot)
- `basket_id` (FK)
- `promo_code_id` (FK)
- `created_at`, `updated_at`

## 📚 API Endpoints

### 🔓 Public Endpoints

#### Health Check
```http
GET /baskets/health
```
**Response:**
```json
{
  "service": "baskets-service",
  "status": "healthy",
  "timestamp": "2025-09-24T07:13:22.271711Z"
}
```

#### Validate Promo Code
```http
POST /baskets/promo-codes/validate
```
**Body:**
```json
{
  "code": "WELCOME10"
}
```
**Response:**
```json
{
  "success": true,
  "message": "Promo code is valid",
  "data": {
    "id": 1,
    "name": "Bienvenue 10%",
    "code": "WELCOME10",
    "discount": "10.00",
    "type": {
      "id": 1,
      "name": "Pourcentage",
      "symbol": "%"
    }
  }
}
```

### 🔐 Protected User Endpoints

#### Get Current User's Basket
```http
GET /baskets/baskets/current
```
**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "amount": "26.74",
    "user_id": 1,
    "items": [
      {
        "id": 1,
        "product_id": 14,
        "quantity": 1,
        "price_ht": "36.74"
      }
    ],
    "promo_codes": [
      {
        "id": 1,
        "code": "WELCOME10",
        "discount": "10.00"
      }
    ]
  }
}
```

#### Add Item to Basket
```http
POST /baskets/baskets/items
```
**Body:**
```json
{
  "product_id": 15,
  "quantity": 2,
  "price_ht": 25.99
}
```

#### Update Item Quantity
```http
PUT /baskets/baskets/items/{item_id}
```
**Body:**
```json
{
  "quantity": 3
}
```

#### Remove Item from Basket
```http
DELETE /baskets/baskets/items/{item_id}
```

#### Apply Promo Code
```http
POST /baskets/baskets/promo-codes
```
**Body:**
```json
{
  "code": "SUMMER15"
}
```

#### Remove Promo Code
```http
DELETE /baskets/baskets/promo-codes/{promo_code_id}
```

#### Clear Basket
```http
DELETE /baskets/baskets/clear
```

### 👑 Admin Endpoints

Require admin authentication (`kylian@collect-verything.com` or `admin@flippad.com`)

#### List All Baskets
```http
GET /baskets/admin/baskets
```

#### Get Specific Basket
```http
GET /baskets/admin/baskets/{id}
```

#### Delete Basket
```http
DELETE /baskets/admin/baskets/{id}
```

#### Promo Codes Management
```http
GET /baskets/admin/promo-codes      # List all
POST /baskets/admin/promo-codes     # Create new
GET /baskets/admin/promo-codes/{id} # Get specific
PUT /baskets/admin/promo-codes/{id} # Update
DELETE /baskets/admin/promo-codes/{id} # Delete
```

**Create Promo Code Body:**
```json
{
  "name": "Nouvelle Réduction",
  "code": "NEWCODE20",
  "discount": 20.00,
  "id_1": 1
}
```

#### Types Management
```http
GET /baskets/admin/types      # List all types
POST /baskets/admin/types     # Create new type
GET /baskets/admin/types/{id} # Get specific type
PUT /baskets/admin/types/{id} # Update type
DELETE /baskets/admin/types/{id} # Delete type
```

**Create Type Body:**
```json
{
  "name": "Nouveau Type",
  "symbol": "🎁"
}
```

## 💰 Business Logic

### Basket Calculation
- Le montant du panier (`amount`) est calculé automatiquement
- Formule: `subtotal - total_discounts`
- Mise à jour automatique lors d'ajout/suppression d'articles ou codes promo

### Promo Codes
- Peuvent être de différents types: Pourcentage, Montant fixe, Livraison gratuite, etc.
- Un panier peut avoir plusieurs codes promo
- Validation automatique lors de l'application

### Types de Codes Promo Pré-configurés
1. **Pourcentage** (%) - Réduction en pourcentage
2. **Montant fixe** (€) - Réduction en euros
3. **Livraison gratuite** (🚚) - Annule les frais de port
4. **Première commande** (🎁) - Pour nouveaux clients
5. **Fidélité** (⭐) - Pour clients réguliers

## 🧪 Testing

### Données de Test
Le service est livré avec des données de test réalistes :
- 5 types de codes promo
- 12 codes promo actifs
- 5 paniers utilisateurs avec articles
- 3 paniers vides

### Codes Promo de Test Disponibles
- `WELCOME10` - 10% de réduction
- `BLACKFRIDAY25` - 25% de réduction
- `SUMMER15` - 15% de réduction
- `SAVE5` - 5€ de réduction
- `BIG20` - 20€ de réduction
- `FREESHIP` - Livraison gratuite (7.99€)
- `FIRST20` - 20% première commande
- `VIP30` - 30% clients VIP

## 🔧 Development

### Commands
```bash
# Start service
docker-compose up -d baskets-service

# Run migrations
docker-compose exec baskets-service php artisan migrate

# Seed database
docker-compose exec baskets-service php artisan db:seed

# View logs
docker-compose logs -f baskets-service

# Access service shell
docker-compose exec baskets-service bash
```

### Service Dependencies
- **baskets-db** - MySQL database (port 3319)
- **shared** - JWT middleware and User model
- **nginx** - Reverse proxy routing

## 🐛 Troubleshooting

### Common Issues

1. **JWT Authentication Failed**
   - Vérifier que le token n'est pas expiré
   - S'assurer que le header Authorization est correct
   - Le middleware supporte `user_id` et `sub` dans le payload

2. **Admin Access Denied**
   - Vérifier que l'email dans le token est dans la liste des admins
   - Emails autorisés: `kylian@collect-verything.com`, `admin@flippad.com`

3. **Database Connection Issues**
   - Vérifier que baskets-db est démarré: `docker-compose ps baskets-db`
   - Vérifier les variables d'environnement dans `.env`

## 🌐 Integration

### With Other Services
- **auth-service**: Validation JWT tokens
- **products-service**: Références produits dans basket_items
- **future orders-service**: Conversion panier → commande

### Nginx Routing
```nginx
location /baskets/ {
    proxy_pass http://baskets_service/api/;
    # proxy headers...
}
```

## ✅ Status

**Service baskets-service est 100% fonctionnel !**

- ✅ Base de données configurée et migrée
- ✅ Modèles Eloquent avec relations
- ✅ API REST complète (CRUD)
- ✅ Authentification JWT opérationnelle
- ✅ Middleware admin fonctionnel
- ✅ Calculs automatiques des paniers
- ✅ Gestion des codes promo
- ✅ Seeders avec données réalistes
- ✅ Documentation complète
- ✅ Tests manuels validés