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

### 4. Test des opérations admin
1. **Create Product** : Créer un nouveau produit
2. **Update Product Stock** : Modifier le stock
3. **Create Brand** : Créer une nouvelle marque

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

## Nouveautés du schéma

Cette collection reflète le nouveau schéma de base de données qui inclut :

- **Système de marques** : Gestion complète des marques
- **Types de produits** : Classification par type (Smartphone, Laptop, etc.)
- **Catalogues** : Regroupement de produits en catalogues
- **Taux de TVA** : Gestion des taux de TVA
- **Relations many-to-many** : Produits liés à plusieurs types, catégories, catalogues
- **Attributs et caractéristiques** : Système d'attributs avancé (à venir)

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
```

Cette collection est maintenant prête pour tester toutes les fonctionnalités du nouveau service produits avec le schéma de base de données migré.