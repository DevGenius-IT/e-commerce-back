# Guide d'utilisation Postman - E-commerce Microservices API

## Vue d'ensemble

Cette collection Postman contient tous les endpoints pour tester l'API e-commerce microservices avec le nouveau schéma de base de données du service produits.

## Configuration initiale

### 1. Importer la collection et l'environnement

1. Ouvrez Postman
2. Importez `E-commerce Microservices API.postman_collection.json`
3. Importez `Local Environment.postman_environment.json`
4. Sélectionnez l'environnement "Local Environment"

### 2. Variables d'environnement

Les variables suivantes sont pré-configurées :

- `base_url` : http://localhost:8000 (API Gateway)
- `admin_email` : admin@example.com
- `admin_password` : password
- `jwt_token` : (auto-rempli après login)
- `user_id` : (auto-rempli après login)
- `test_*_id` : IDs d'exemple pour les tests

## Structure de la collection

### 🔍 Health & Status
- **API Gateway Health** : Vérifie la santé du gateway
- **Products Service Health** : Vérifie la santé du service produits
- **Auth Service Health** : Vérifie la santé du service d'authentification
- **Addresses Service Health** : Vérifie la santé du service d'adresses

### 🔐 Authentication
- **Login (Gateway)** : Connexion via l'API Gateway
- **Login (Auth Service)** : Connexion directe au service d'authentification
- **Register** : Inscription d'un nouvel utilisateur
- **Get Profile** : Récupération du profil utilisateur
- **Validate Token** : Validation du token JWT
- **Logout** : Déconnexion

### 👥 Users & Roles
- **List Roles** : Liste des rôles disponibles
- **List Permissions** : Liste des permissions
- **Assign Role to User** : Attribution d'un rôle à un utilisateur

### 🏠 Addresses
- **List Countries** : Liste des pays
- **Get Country Details** : Détails d'un pays
- **Get Country Regions** : Régions d'un pays
- **List My Addresses** : Adresses de l'utilisateur connecté
- **Create Address** : Création d'une nouvelle adresse
- **Get Address by Type** : Récupération d'adresse par type

### 🛍️ Products (Nouveau schéma)

#### 📦 Products Management
- **List Products** : Liste des produits avec filtres et pagination
- **Search Products** : Recherche de produits
- **Get Product Details** : Détails d'un produit
- **Filter Products by Brand** : Filtrage par marque
- **Filter Products by Category** : Filtrage par catégorie
- **Filter Products by Type** : Filtrage par type
- **Filter Products by Price Range** : Filtrage par gamme de prix
- **Filter Products In Stock** : Produits en stock uniquement

#### 🏷️ Brands
- **List Brands** : Liste des marques
- **Get Brand Details** : Détails d'une marque
- **Get Brand Products** : Produits d'une marque

#### 📋 Categories
- **List Categories** : Liste des catégories
- **Get Category Details** : Détails d'une catégorie
- **Get Category Products** : Produits d'une catégorie

#### 🏷️ Types
- **List Types** : Liste des types de produits
- **Get Type Details** : Détails d'un type
- **Get Type Products** : Produits d'un type

#### 📚 Catalogs
- **List Catalogs** : Liste des catalogues
- **Get Catalog Details** : Détails d'un catalogue
- **Get Catalog Products** : Produits d'un catalogue

#### 💰 VAT Rates
- **List VAT Rates** : Liste des taux de TVA
- **Get VAT Rate Details** : Détails d'un taux de TVA

### 🛒 Baskets (Nouveaux endpoints)

#### 🛍️ Public & User Endpoints
- **Get Current User Basket** : Panier actuel de l'utilisateur connecté
- **Add Product to Basket** : Ajouter un produit au panier
- **Remove Product from Basket** : Retirer un produit du panier
- **Update Product Quantity** : Modifier la quantité d'un produit
- **Clear Basket** : Vider le panier
- **Apply Promo Code** : Appliquer un code promo
- **Remove Promo Code** : Retirer un code promo
- **Get Basket Items** : Liste des articles du panier
- **Get Basket Summary** : Résumé du panier avec totaux

#### 🎟️ Promo Codes (Public)
- **List Available Promo Codes** : Codes promo disponibles
- **Get Promo Code Details** : Détails d'un code promo
- **Validate Promo Code** : Vérifier la validité d'un code

### 📨 Messages
- **Messages Health** : Santé du service de messages
- **Publish Message** : Publication d'un message
- **List Failed Messages** : Messages en échec
- **List Queues** : Liste des files d'attente

### 🔧 Admin Operations (Authentification requise)

#### 📦 Product Management
- **Create Product** : Création d'un produit
- **Update Product** : Mise à jour d'un produit
- **Delete Product** : Suppression d'un produit
- **Update Product Stock** : Gestion du stock

#### 🏷️ Brand Management
- **Create Brand** : Création d'une marque
- **Update Brand** : Mise à jour d'une marque
- **Delete Brand** : Suppression d'une marque

#### 📋 Category Management
- **Create Category** : Création d'une catégorie
- **Update Category** : Mise à jour d'une catégorie
- **Delete Category** : Suppression d'une catégorie

#### 🏷️ Type Management
- **Create Type** : Création d'un type
- **Update Type** : Mise à jour d'un type
- **Delete Type** : Suppression d'un type

#### 📚 Catalog Management
- **Create Catalog** : Création d'un catalogue
- **Update Catalog** : Mise à jour d'un catalogue
- **Delete Catalog** : Suppression d'un catalogue

#### 💰 VAT Management
- **Create VAT Rate** : Création d'un taux de TVA
- **Update VAT Rate** : Mise à jour d'un taux de TVA
- **Delete VAT Rate** : Suppression d'un taux de TVA

#### 🛒 Basket Management
- **List All Baskets** : Voir tous les paniers (admin)
- **Get User Baskets** : Paniers d'un utilisateur spécifique

#### 🎟️ Promo Code Management
- **Create Promo Code** : Créer un nouveau code promo
- **Update Promo Code** : Modifier un code promo
- **Delete Promo Code** : Supprimer un code promo
- **List All Promo Codes** : Voir tous les codes promo

#### 🏷️ Promo Type Management
- **List Promo Types** : Types de codes promo
- **Create Promo Type** : Créer un type de promo
- **Update Promo Type** : Modifier un type de promo
- **Delete Promo Type** : Supprimer un type de promo

## Guide de test rapide

### 1. Vérification des services
1. Exécutez **API Gateway Health**
2. Exécutez **Products Service Health**
3. Exécutez **Auth Service Health**

### 2. Authentification
1. Exécutez **Login (Gateway)** avec les credentials par défaut
2. Le token JWT sera automatiquement sauvegardé

### 3. Test des produits (nouvelles fonctionnalités)
1. **List Products** : Voir tous les produits avec relations
2. **Search Products** : Rechercher "iPhone"
3. **List Brands** : Voir les marques disponibles
4. **List Categories** : Voir les catégories
5. **Filter Products by Brand** : Filtrer par marque Apple (ID: 1)

### 4. Test des paniers
1. **Get Current User Basket** : Voir le panier actuel
2. **Add Product to Basket** : Ajouter un produit (ID: 1, quantity: 2)
3. **Apply Promo Code** : Utiliser le code "WELCOME10"
4. **Get Basket Summary** : Voir les totaux avec remise

### 5. Test des opérations admin
1. **Create Product** : Créer un nouveau produit
2. **Update Product Stock** : Modifier le stock
3. **Create Brand** : Créer une nouvelle marque
4. **Create Promo Code** : Créer un code promo

## Exemples de données de test

### Création d'un produit
```json
{
    "name": "Nouveau Smartphone",
    "ref": "NEW-PHONE-001",
    "price_ht": 599.99,
    "stock": 50,
    "id_1": 1,
    "type_ids": [1],
    "category_ids": [1, 3],
    "catalog_ids": [1]
}
```

### Mise à jour du stock
```json
{
    "stock": 20,
    "operation": "increment"
}
```

### Création d'une marque
```json
{
    "name": "Nouvelle Marque"
}
```

### Ajout d'un produit au panier
```json
{
    "product_id": 1,
    "quantity": 2
}
```

### Création d'un code promo
```json
{
    "code": "NEWCODE20",
    "description": "Réduction de 20%",
    "type_id": 1,
    "value": 20.00,
    "is_active": true,
    "usage_limit": 100,
    "start_date": "2024-01-01",
    "end_date": "2024-12-31"
}
```

## Filtres disponibles pour les produits

- `search` : Recherche dans le nom et la référence
- `brand_id` : Filtrage par marque
- `category_id` : Filtrage par catégorie
- `type_id` : Filtrage par type
- `catalog_id` : Filtrage par catalogue
- `in_stock` : Produits en stock (true/false)
- `min_price` / `max_price` : Gamme de prix
- `sort_by` : Tri (name, ref, price_ht, stock, created_at)
- `sort_order` : Ordre (asc, desc)
- `per_page` : Nombre d'éléments par page

## Nouveautés des services

Cette collection inclut maintenant :

### Service Produits (products-service)
- **Système de marques** : Gestion complète des marques
- **Types de produits** : Classification par type (Smartphone, Laptop, etc.)
- **Catalogues** : Regroupement de produits en catalogues
- **Taux de TVA** : Gestion des taux de TVA
- **Relations many-to-many** : Produits liés à plusieurs types, catégories, catalogues

### Service Paniers (baskets-service) 🆕
- **Gestion de paniers** : Création automatique et gestion des paniers utilisateurs
- **Articles de panier** : Ajout, modification, suppression de produits
- **Codes promo** : Système complet de codes promotionnels avec types
- **Calculs automatiques** : Totaux HT, TTC et remises calculés automatiquement
- **Relations complexes** : Paniers liés aux codes promo avec gestion many-to-many

## Troubleshooting

### Erreur 401 (Unauthorized)
- Vérifiez que vous êtes connecté
- Re-exécutez **Login (Gateway)**

### Erreur 404 (Not Found)
- Vérifiez que les services sont démarrés : `make start`
- Vérifiez les IDs utilisés dans les variables

### Erreur 500 (Internal Server Error)
- Vérifiez les logs : `make logs-service SERVICE=products-service`
- Vérifiez que les migrations sont appliquées : `make migrate-all`

## Variables utiles

```
{{base_url}} - URL de base de l'API Gateway
{{jwt_token}} - Token d'authentification (auto-rempli)
{{test_product_id}} - ID de produit de test
{{test_brand_id}} - ID de marque de test
{{test_category_id}} - ID de catégorie de test
{{test_basket_id}} - ID de panier de test
{{test_promo_code}} - Code promo de test ("WELCOME10")
{{test_type_id}} - ID de type de promo de test
```

Cette collection est maintenant prête pour tester toutes les fonctionnalités des services produits et paniers avec leurs schémas de base de données complets.