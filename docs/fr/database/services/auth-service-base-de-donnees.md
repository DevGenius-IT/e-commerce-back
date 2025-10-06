# Documentation de la Base de Données du Service d'Authentification

## Table des Matières
- [Vue d'ensemble](#vue-densemble)
- [Informations sur la Base de Données](#informations-sur-la-base-de-données)
- [Diagramme de Relations Entre Entités](#diagramme-de-relations-entre-entités)
- [Schémas des Tables](#schémas-des-tables)
- [Détails de l'Architecture](#détails-de-larchitecture)
- [Gestion des Rôles et Permissions](#gestion-des-rôles-et-permissions)
- [Événements Publiés](#événements-publiés)
- [Références Inter-Services](#références-inter-services)
- [Index et Performance](#index-et-performance)

## Vue d'ensemble

La base de données du service d'authentification (`auth_service_db`) gère l'authentification des utilisateurs et le système d'autorisation basé sur les rôles via le package Spatie Laravel Permission. Ce service fournit une authentification centralisée pour toute la plateforme de microservices, en publiant des événements pour synchroniser les données utilisateur à travers les services.

**Service :** auth-service
**Base de Données :** auth_service_db
**Port Externe :** 3302
**Total des Tables :** 10 (1 métier, 9 permissions Spatie)

**Capacités Clés :**
- Authentification JWT avec gestion des jetons
- Contrôle d'accès basé sur les rôles (RBAC) via Spatie Permission
- Gestion des utilisateurs avec attributs personnalisés
- Audit des connexions utilisateur et des tentatives échouées
- Suppressions logiques pour la conservation de l'historique
- Publication d'événements pour la synchronisation inter-services

## Informations sur la Base de Données

### Détails de Connexion
```bash
Hôte: localhost (dans le réseau Docker: mysql-auth)
Port: 3302 (externe), 3306 (interne)
Base de Données: auth_service_db
Jeu de Caractères: utf8mb4
Collation: utf8mb4_unicode_ci
Moteur: InnoDB
```

### Configuration d'Environnement
```bash
DB_CONNECTION=mysql
DB_HOST=mysql-auth
DB_PORT=3306
DB_DATABASE=auth_service_db
DB_USERNAME=auth_user
DB_PASSWORD=auth_pass
```

## Diagramme de Relations Entre Entités

```
┌─────────────────────────────────────────────────────────────────────┐
│                     BASE DE DONNÉES DU SERVICE AUTH                 │
│                    auth_service_db (10 tables)                      │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│                         ENTITÉ UTILISATEUR MÉTIER                    │
└──────────────────────────────────────────────────────────────────────┘

    ┌────────────────────────────────┐
    │           users                │
    ├────────────────────────────────┤
    │ id              PK             │
    │ name            VARCHAR(255)   │
    │ email           VARCHAR(255) UK│
    │ password        VARCHAR(255)   │
    │ created_at                     │
    │ updated_at                     │
    │ deleted_at                     │
    └──────────┬─────────────────────┘
               │
               │ Spatie Permission RBAC (via traits de modèle)
               │
               ├────────────────────────┬──────────────────────┐
               │                        │                      │
               ▼                        ▼                      ▼
┌──────────────────────────────────────────────────────────────────────┐
│             SYSTÈME DE GESTION DES PERMISSIONS SPATIE                │
└──────────────────────────────────────────────────────────────────────┘

    ┌─────────────────┐           ┌──────────────────┐
    │  permissions    │           │      roles       │
    ├─────────────────┤           ├──────────────────┤
    │ id          PK  │           │ id           PK  │
    │ name        UK  │           │ name         UK  │
    │ guard_name      │           │ guard_name       │
    │ created_at      │           │ created_at       │
    │ updated_at      │           │ updated_at       │
    └────────┬────────┘           └────────┬─────────┘
             │                             │
             │                             │
             │         ┌───────────────────┘
             │         │
             │         │
             ▼         ▼
    ┌─────────────────────────┐
    │ role_has_permissions    │
    │        (PIVOT)          │
    ├─────────────────────────┤
    │ permission_id   FK      │───────┐
    │ role_id         FK      │─────┐ │
    └─────────────────────────┘     │ │
                                    │ │
    ┌─────────────────────────┐     │ │
    │ model_has_permissions   │     │ │
    │        (PIVOT)          │     │ │
    ├─────────────────────────┤     │ │
    │ permission_id   FK      │─────┘ │
    │ model_type              │       │
    │ model_id                │       │
    └─────────────────────────┘       │
                                      │
    ┌─────────────────────────┐       │
    │   model_has_roles       │       │
    │        (PIVOT)          │       │
    ├─────────────────────────┤       │
    │ role_id         FK      │───────┘
    │ model_type              │
    │ model_id                │
    └─────────────────────────┘

LÉGENDE:
────────  Relation / Clé Étrangère
PK        Clé Primaire
FK        Clé Étrangère
UK        Contrainte Unique
```

## Schémas des Tables

### 1. users (MÉTIER)
Table centrale d'entité utilisateur pour l'authentification et les profils.

| Colonne | Type | Contraintes | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | ID utilisateur |
| name | VARCHAR(255) | NOT NULL | Nom complet de l'utilisateur |
| email | VARCHAR(255) | NOT NULL, UNIQUE | Adresse email (identifiant de connexion) |
| password | VARCHAR(255) | NOT NULL | Mot de passe haché (bcrypt) |
| created_at | TIMESTAMP | NULLABLE | Horodatage d'inscription |
| updated_at | TIMESTAMP | NULLABLE | Horodatage de dernière mise à jour |
| deleted_at | TIMESTAMP | NULLABLE | Horodatage de suppression logique |

**Index :**
- PRIMARY KEY (id)
- UNIQUE (email)
- INDEX (email, deleted_at)

**Règles Métier :**
- Les suppressions logiques sont activées (deleted_at)
- L'email doit être unique parmi les utilisateurs actifs
- Le mot de passe est haché avec bcrypt (coût: 10)
- Intégré avec Spatie Permission via HasRoles trait

**Relations de Modèle :**
- morphToMany: Role (via model_has_roles)
- morphToMany: Permission (via model_has_permissions)

**Méthodes de Modèle :**
```php
// Via HasRoles trait de Spatie
hasRole(string|array $roles): bool
hasPermissionTo(string|Permission $permission): bool
assignRole(string|int|array|Role $role): self
givePermissionTo(string|int|array|Permission $permission): self
```

**Cycle de Vie :**
1. Créé lors de l'inscription
2. Mis à jour lors des modifications de profil
3. Supprimé logiquement sur demande de l'utilisateur (RGPD)
4. Supprimé définitivement après la période de rétention (90 jours)

---

### 2. permissions (Spatie)
Définit les autorisations individuelles dans le système.

| Colonne | Type | Contraintes | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | ID de permission |
| name | VARCHAR(255) | NOT NULL, UNIQUE | Nom de permission (ex. "create_product") |
| guard_name | VARCHAR(255) | NOT NULL | Nom du guard (web, api) |
| created_at | TIMESTAMP | NULLABLE | Horodatage de création |
| updated_at | TIMESTAMP | NULLABLE | Horodatage de dernière mise à jour |

**Index :**
- PRIMARY KEY (id)
- UNIQUE (name, guard_name)

**Règles Métier :**
- Les combinaisons nom + guard_name doivent être uniques
- Les permissions peuvent être attribuées directement aux utilisateurs ou via des rôles
- Gérées par le package Spatie Laravel Permission

**Exemples de Permissions :**
```sql
INSERT INTO permissions (name, guard_name) VALUES
('manage_users', 'api'),
('manage_products', 'api'),
('manage_orders', 'api'),
('view_analytics', 'api'),
('handle_support_tickets', 'api');
```

---

### 3. roles (Spatie)
Définit les rôles pour le regroupement de permissions.

| Colonne | Type | Contraintes | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | ID de rôle |
| name | VARCHAR(255) | NOT NULL, UNIQUE | Nom de rôle (ex. "admin") |
| guard_name | VARCHAR(255) | NOT NULL | Nom du guard (web, api) |
| created_at | TIMESTAMP | NULLABLE | Horodatage de création |
| updated_at | TIMESTAMP | NULLABLE | Horodatage de dernière mise à jour |

**Index :**
- PRIMARY KEY (id)
- UNIQUE (name, guard_name)

**Règles Métier :**
- Les combinaisons nom + guard_name doivent être uniques
- Les rôles peuvent avoir plusieurs permissions
- Les utilisateurs peuvent avoir plusieurs rôles
- Gérés par le package Spatie Laravel Permission

**Exemples de Rôles :**
```sql
INSERT INTO roles (name, guard_name) VALUES
('super_admin', 'api'),
('admin', 'api'),
('customer_service', 'api'),
('customer', 'api'),
('guest', 'api');
```

---

### 4. model_has_permissions (Pivot Spatie)
Attribue les permissions directement aux utilisateurs (contourne les rôles).

| Colonne | Type | Contraintes | Description |
|--------|------|-------------|-------------|
| permission_id | BIGINT UNSIGNED | PK, FK | Référence de permission |
| model_type | VARCHAR(255) | PK | Type de modèle (App\\Models\\User) |
| model_id | BIGINT UNSIGNED | PK | ID de modèle (user_id) |

**Clés Étrangères :**
- permission_id → permissions(id) ON DELETE CASCADE

**Index :**
- PRIMARY KEY (permission_id, model_id, model_type)
- INDEX (model_id, model_type)

**Règles Métier :**
- Les permissions sont supprimées en cascade lorsque la permission est supprimée
- Permet des substitutions de permissions spécifiques à l'utilisateur
- model_type utilise le polymorphisme (généralement 'App\\Models\\User')

**Cas d'Utilisation :**
- Accorder des permissions spéciales à un utilisateur sans changer leur rôle
- Révocations temporaires de permissions

---

### 5. model_has_roles (Pivot Spatie)
Attribue les rôles aux utilisateurs.

| Colonne | Type | Contraintes | Description |
|--------|------|-------------|-------------|
| role_id | BIGINT UNSIGNED | PK, FK | Référence de rôle |
| model_type | VARCHAR(255) | PK | Type de modèle (App\\Models\\User) |
| model_id | BIGINT UNSIGNED | PK | ID de modèle (user_id) |

**Clés Étrangères :**
- role_id → roles(id) ON DELETE CASCADE

**Index :**
- PRIMARY KEY (role_id, model_id, model_type)
- INDEX (model_id, model_type)

**Règles Métier :**
- Les rôles sont supprimés en cascade lorsque le rôle est supprimé
- Les utilisateurs peuvent avoir plusieurs rôles
- model_type utilise le polymorphisme (généralement 'App\\Models\\User')

**Utilisation :**
```php
// Attribuer un rôle à un utilisateur
$user->assignRole('admin');

// Vérifier si l'utilisateur a un rôle
$user->hasRole('admin'); // true
```

---

### 6. role_has_permissions (Pivot Spatie)
Attribue les permissions aux rôles.

| Colonne | Type | Contraintes | Description |
|--------|------|-------------|-------------|
| permission_id | BIGINT UNSIGNED | PK, FK | Référence de permission |
| role_id | BIGINT UNSIGNED | PK, FK | Référence de rôle |

**Clés Étrangères :**
- permission_id → permissions(id) ON DELETE CASCADE
- role_id → roles(id) ON DELETE CASCADE

**Index :**
- PRIMARY KEY (permission_id, role_id)

**Règles Métier :**
- Suppression en cascade des deux côtés
- Permet la définition des capacités des rôles
- Les utilisateurs héritent des permissions via les rôles

**Utilisation :**
```php
// Attribuer des permissions à un rôle
$role = Role::findByName('admin');
$role->givePermissionTo('manage_users');
$role->givePermissionTo('manage_products');

// L'utilisateur avec le rôle 'admin' a maintenant ces permissions
```

---

## Détails de l'Architecture

### Intégration de Spatie Permission

Le service d'authentification utilise **Spatie Laravel Permission** pour le contrôle d'accès basé sur les rôles. Ce package fournit :

1. **Permissions :** Autorisations granulaires (ex. "create_product", "delete_order")
2. **Rôles :** Regroupements de permissions (ex. "admin", "customer")
3. **Attribution Directe :** Les utilisateurs peuvent avoir des permissions directement ou via des rôles
4. **Garde-fous :** Support de plusieurs gardes (web, api) pour différents contextes d'authentification

**Relations de Permission :**
```
User
  ├─ Direct Permissions (via model_has_permissions)
  │   └─ "manage_own_profile"
  │
  └─ Roles (via model_has_roles)
      ├─ Role: "customer_service"
      │   ├─ Permission: "view_orders"
      │   ├─ Permission: "update_orders"
      │   └─ Permission: "handle_tickets"
      │
      └─ Role: "admin"
          ├─ Permission: "manage_users"
          ├─ Permission: "manage_products"
          └─ Permission: "*" (toutes permissions)
```

### Configuration des Gardes

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

Les permissions et les rôles sont créés par garde :
```php
// Guard 'web' pour l'authentification par session
Permission::create(['name' => 'manage_users', 'guard_name' => 'web']);

// Guard 'api' pour l'authentification JWT (plateforme microservices)
Permission::create(['name' => 'manage_users', 'guard_name' => 'api']);
```

### Stratégie de Suppression Logique

**Activée sur :**
- **users :** Préserver l'historique pour la conformité RGPD
- Permet de restaurer les utilisateurs accidentellement supprimés
- Maintient l'intégrité référentielle dans les autres services

**Pas de Suppression Logique sur :**
- Tables de Spatie Permission (données de référence)
- Tables pivots (supprimées en cascade)

**Politique de Rétention :**
```sql
-- Nettoyer les utilisateurs définitivement supprimés (90 jours)
DELETE FROM users
WHERE deleted_at IS NOT NULL
  AND deleted_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Authentification JWT

Le service utilise `tymon/jwt-auth` pour l'authentification sans état :

**Flux de Jetons :**
1. L'utilisateur se connecte avec email + mot de passe
2. Le service génère un jeton JWT
3. Le client inclut le jeton dans les requêtes suivantes
4. Le middleware valide le jeton pour chaque requête

**Structure du Jeton :**
```json
{
  "sub": 123,
  "name": "John Doe",
  "email": "john@example.com",
  "roles": ["admin"],
  "permissions": ["manage_users", "manage_products"],
  "iat": 1696348800,
  "exp": 1696352400
}
```

## Gestion des Rôles et Permissions

### Hiérarchie des Rôles

```
super_admin
  ├─ Permissions: * (toutes)
  └─ Capacités: Gestion complète du système

admin
  ├─ Permissions: manage_users, manage_products, manage_orders
  └─ Capacités: Administration de la plateforme

customer_service
  ├─ Permissions: view_orders, update_orders, handle_tickets
  └─ Capacités: Support et traitement des commandes

customer
  ├─ Permissions: place_orders, view_own_orders, manage_own_profile
  └─ Capacités: Opérations standard des clients

guest
  ├─ Permissions: view_products
  └─ Capacités: Navigation en lecture seule
```

### Vérifications de Permissions

```php
// Via Middleware
Route::middleware(['permission:manage_users'])->group(function () {
    Route::post('/users', [UserController::class, 'store']);
});

// Via les Méthodes du Modèle
if ($user->hasPermissionTo('manage_products')) {
    // L'utilisateur peut gérer les produits
}

// Via les Gates
Gate::allows('manage_orders'); // true/false

// Permissions Multiples (OU logique)
$user->hasAnyPermission(['manage_users', 'view_users']);

// Permissions Multiples (ET logique)
$user->hasAllPermissions(['manage_users', 'delete_users']);
```

### Vérifications de Rôles

```php
// Via Middleware
Route::middleware(['role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});

// Via les Méthodes du Modèle
if ($user->hasRole('admin')) {
    // L'utilisateur a le rôle admin
}

// Rôles Multiples (OU logique)
$user->hasAnyRole(['admin', 'super_admin']);

// Vérifier le rôle ET la permission
$user->hasRole('admin') && $user->hasPermissionTo('delete_users');
```

### Attribuer des Permissions

```php
// Permissions Directes aux Utilisateurs
$user->givePermissionTo('manage_products');

// Permissions aux Rôles
$role = Role::findByName('customer_service');
$role->givePermissionTo(['view_orders', 'update_orders']);

// Attribuer des Rôles aux Utilisateurs
$user->assignRole('admin');

// Synchroniser les Permissions (remplacer toutes les permissions)
$user->syncPermissions(['manage_users', 'view_analytics']);

// Synchroniser les Rôles
$user->syncRoles(['admin', 'customer_service']);
```

### Révoquer des Permissions

```php
// Révoquer une Permission Directe
$user->revokePermissionTo('manage_products');

// Révoquer un Rôle
$user->removeRole('admin');

// Révoquer Toutes les Permissions
$user->permissions()->detach();

// Révoquer Tous les Rôles
$user->roles()->detach();
```

## Événements Publiés

Le service d'authentification publie des événements vers RabbitMQ pour la synchronisation inter-services.

### Schéma d'Événement

Tous les événements suivent le format de message standard :
```json
{
  "event": "user.created",
  "timestamp": "2025-10-03T14:30:00Z",
  "data": {
    "user_id": 123,
    "email": "user@example.com",
    "name": "John Doe"
  }
}
```

### 1. UserCreated
**File d'Attente :** auth.user.created
**Publié :** Lorsqu'un nouvel utilisateur s'inscrit avec succès

**Charge Utile :**
```json
{
  "event": "user.created",
  "timestamp": "2025-10-03T14:30:00Z",
  "data": {
    "user_id": 123,
    "email": "john.doe@example.com",
    "name": "John Doe",
    "roles": ["customer"],
    "created_at": "2025-10-03T14:30:00Z"
  }
}
```

**Consommateurs :**
- baskets-service : Créer un panier vide pour le nouvel utilisateur
- addresses-service : Initialiser le carnet d'adresses
- orders-service : Activer la passation de commandes
- contacts-service : Ajouter à la gestion des contacts

---

### 2. UserUpdated
**File d'Attente :** auth.user.updated
**Publié :** Lorsque les détails de l'utilisateur sont modifiés

**Charge Utile :**
```json
{
  "event": "user.updated",
  "timestamp": "2025-10-03T15:00:00Z",
  "data": {
    "user_id": 123,
    "updated_fields": {
      "name": "John Smith",
      "email": "john.smith@example.com"
    },
    "updated_at": "2025-10-03T15:00:00Z"
  }
}
```

**Consommateurs :**
- Tous les services : Invalider les données utilisateur mises en cache
- newsletters-service : Mettre à jour les informations des abonnés

---

### 3. UserDeleted
**File d'Attente :** auth.user.deleted
**Publié :** Lorsque l'utilisateur est supprimé logiquement

**Charge Utile :**
```json
{
  "event": "user.deleted",
  "timestamp": "2025-10-03T16:00:00Z",
  "data": {
    "user_id": 123,
    "deleted_at": "2025-10-03T16:00:00Z",
    "anonymize": true
  }
}
```

**Consommateurs :**
- baskets-service : Archiver les paniers utilisateur
- orders-service : Anonymiser l'historique des commandes (conformité RGPD)
- addresses-service : Supprimer les adresses utilisateur
- sav-service : Anonymiser les tickets de support

---

### 4. RoleAssigned
**File d'Attente :** auth.role.assigned
**Publié :** Lorsqu'un rôle est attribué à un utilisateur

**Charge Utile :**
```json
{
  "event": "role.assigned",
  "timestamp": "2025-10-03T14:45:00Z",
  "data": {
    "user_id": 123,
    "role_id": 5,
    "role_name": "customer_service",
    "assigned_at": "2025-10-03T14:45:00Z"
  }
}
```

**Consommateurs :**
- sav-service : Ajouter au pool d'affectation d'agents
- contacts-service : Accorder l'accès aux contacts

---

### Configuration RabbitMQ

**Échange :** auth_exchange (topic)
**Clés de Routage :**
- user.created
- user.updated
- user.deleted
- role.assigned
- permission.granted

**Files d'Attente Consommatrices :**
- baskets-service : auth.events.baskets
- orders-service : auth.events.orders
- addresses-service : auth.events.addresses
- sav-service : auth.events.sav
- contacts-service : auth.events.contacts

## Références Inter-Services

### Référencé PAR D'Autres Services

Presque tous les services font référence à user_id depuis le service d'authentification :

#### baskets-service
```sql
-- Table baskets
baskets.user_id → auth-service.users.id (FK virtuelle)
```

**Synchronisation :**
- Écouter UserCreated : Initialiser le contexte du panier
- Écouter UserDeleted : Archiver les paniers utilisateur

---

#### orders-service
```sql
-- Table orders
orders.user_id → auth-service.users.id (FK virtuelle)
```

**Synchronisation :**
- Écouter UserDeleted : Anonymiser l'historique des commandes
- Validation user_id avant la création de commande

---

#### addresses-service
```sql
-- Table addresses
addresses.user_id → auth-service.users.id (FK virtuelle)
```

**Synchronisation :**
- Écouter UserCreated : Initialiser le carnet d'adresses
- Écouter UserDeleted : Supprimer les adresses

---

#### sav-service
```sql
-- Table tickets
tickets.user_id → auth-service.users.id (FK virtuelle)
```

**Synchronisation :**
- Écouter RoleAssigned : Mettre à jour le pool d'agents
- Écouter UserDeleted : Anonymiser les tickets (conformité RGPD)

---

### Aucune Référence Directe VERS D'Autres Services

Le service d'authentification est indépendant et ne fait référence à aucun autre service dans son schéma de base de données. Cette indépendance garantit :
- Capacité de déploiement autonome
- Pas de dépendances circulaires
- Haute disponibilité (ne dépend d'aucun autre service)

## Index et Performance

### Index Stratégiques

#### Table users
```sql
UNIQUE (email)                -- Recherche de connexion
INDEX (email, deleted_at)     -- Unicité de l'email actif
```

**Optimisation des Requêtes :**
```sql
-- Recherche de connexion rapide
SELECT * FROM users WHERE email = 'john@example.com' AND deleted_at IS NULL;

-- Unicité de l'email (utilisateurs actifs uniquement)
SELECT COUNT(*) FROM users WHERE email = 'john@example.com' AND deleted_at IS NULL;
```

---

#### Tables Spatie Permission
```sql
-- Table permissions
UNIQUE (name, guard_name)

-- Table roles
UNIQUE (name, guard_name)

-- Tables Pivot
INDEX (model_id, model_type) sur model_has_permissions
INDEX (model_id, model_type) sur model_has_roles
```

**Optimisation des Requêtes :**
```sql
-- Obtenir toutes les permissions d'un utilisateur (via rôles + direct)
SELECT p.name
FROM permissions p
WHERE p.id IN (
    -- Permissions directes
    SELECT permission_id FROM model_has_permissions
    WHERE model_type = 'App\\Models\\User' AND model_id = 123

    UNION

    -- Permissions via rôles
    SELECT rhp.permission_id
    FROM role_has_permissions rhp
    JOIN model_has_roles mhr ON rhp.role_id = mhr.role_id
    WHERE mhr.model_type = 'App\\Models\\User' AND mhr.model_id = 123
);
```

---

### Recommandations de Performance

1. **Requêtes Utilisateur :**
   - Toujours inclure `deleted_at IS NULL` pour les utilisateurs actifs
   - Utiliser l'index email pour les recherches de connexion
   - Mettre en cache les données utilisateur fréquemment accédées

2. **Vérifications de Permissions :**
   - Mise en cache des permissions par utilisateur (Redis) pour réduire les requêtes
   - Charger en eager les rôles et permissions : `$user->load('roles.permissions', 'permissions')`
   - Éviter les requêtes N+1 avec `with('roles')`

3. **Validation JWT :**
   - Inclure les rôles et permissions dans la charge utile du jeton
   - Réduire les requêtes en base de données pour la vérification des permissions
   - Mettre en cache la liste noire des jetons en Redis

4. **Requêtes de Rôle :**
   - Mettre en cache les définitions de rôles (rarement modifiés)
   - Utiliser l'index de nom unique pour la recherche de rôles
   - Grouper les vérifications de permissions

5. **Suppressions Logiques :**
   - Toujours filtrer `deleted_at IS NULL` dans les requêtes
   - Considérer l'archivage des anciens utilisateurs supprimés (>90 jours)
   - Créer un index : `(deleted_at, created_at)` pour les requêtes de nettoyage

---

**Version du Document :** 1.0
**Dernière Mise à Jour :** 2025-10-03
**Version de la Base de Données :** MySQL 8.0
**Version de Laravel :** 12.x
**Package Spatie :** spatie/laravel-permission ^6.0
