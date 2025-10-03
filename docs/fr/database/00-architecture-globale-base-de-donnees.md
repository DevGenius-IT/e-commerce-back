# Architecture Globale de la Base de Donnees

## Vue d'ensemble de la plateforme

Cette plateforme e-commerce est construite sur une architecture de microservices avec isolation des bases de donnees. Chaque service gere sa propre base de donnees MySQL, communiquant de maniere asynchrone via RabbitMQ et stockage d'objets MinIO.

**Architecture Cle:**
- **Pattern:** Base de donnees par service (isolation complete)
- **Communication:** Messagerie asynchrone evenementielle
- **Stockage:** MinIO distribue (compatible S3)
- **Total Services:** 12 microservices actifs
- **Total Bases de Donnees:** 12 bases MySQL independantes

## Liste des Services et Bases de Donnees

| Service | Base de Donnees | Port Externe | Tables | Purpose |
|---------|-----------------|--------------|--------|---------|
| api-gateway | N/A | 8100 | - | Point d'entree unique, routage |
| auth-service | auth_service_db | 3308 | 6 | Authentification JWT, roles/permissions |
| messages-broker | N/A | - | - | Gestion RabbitMQ, coordination inter-services |
| addresses-service | addresses_service_db | 3318 | 2 | Gestion des adresses de facturation/livraison |
| products-service | products_service_db | 3307 | 15 | Catalogue produits, inventaire, taxonomie |
| baskets-service | baskets_service_db | 3319 | 5 | Paniers d'achat, codes promo |
| orders-service | orders_service_db | 3330 | 3 | Commandes, machine d'etats, calculs taxes |
| deliveries-service | deliveries_service_db | 3331 | 3 | Suivi livraison, gestion transporteurs |
| newsletters-service | newsletters_service_db | 3310 | 4 | Campagnes emails, abonnements |
| sav-service | sav_service_db | 3311 | 4 | Service client (SAV), tickets support |
| contacts-service | contacts_service_db | 3309 | 1 | Formulaires de contact |
| websites-service | websites_service_db | 3312 | 1 | Configuration multi-sites |
| questions-service | questions_service_db | 3313 | 1 | Systeme FAQ |

## Architecture Multi-Services

### Flux de Communication
```
Client → Nginx (80/443) → API Gateway (8100) → RabbitMQ → Microservices
                                                    ↓
                                              Stockage MinIO
```

### Caracteristiques Cles
1. **Isolation des Bases de Donnees:**
   - Chaque service possede sa propre base MySQL
   - Aucune contrainte de cle etrangere inter-services
   - Donnees synchronisees via evenements
   - Schema evolutif independant

2. **Messagerie Asynchrone:**
   - RabbitMQ pour toutes les communications inter-services
   - Echanges bases sur des topics
   - Files de consommateurs par service
   - Patterns de saga pour workflows complexes

3. **Stockage d'Objets (MinIO):**
   - API compatible S3 avec SDK AWS
   - 3 buckets: products (images), sav (pieces jointes), newsletters (modeles)
   - URLs presignees pour acces temporaire securise
   - Console: http://localhost:9001

4. **Passerelle API:**
   - Point d'entree HTTP unique
   - Orchestration des appels de services
   - Gestion de l'authentification
   - Traçabilite des requetes (X-Request-ID)

## Diagramme d'Architecture de la Base de Donnees

```
┌──────────────────────────────────────────────────────────────────────┐
│            ARCHITECTURE DE BASE DE DONNEES E-COMMERCE                │
│                  12 Bases MySQL + MinIO + RabbitMQ                   │
└──────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                    COUCHE AUTHENTIFICATION                          │
└────────────────────────────────────────────────────────────────────┘

    ┌─────────────────────────┐
    │  auth_service_db        │
    │  Port: 3308             │
    ├─────────────────────────┤
    │  • users                │ ← Utilisateurs et authentification
    │  • roles                │ ← Definitions des roles
    │  • permissions          │ ← Definitions des permissions
    │  • role_has_permissions │ ← Affectations role-permission
    │  • model_has_roles      │ ← Affectations utilisateur-role
    │  • model_has_permissions│ ← Permissions utilisateur directes
    └─────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                   COUCHE CATALOGUE PRODUITS                         │
└────────────────────────────────────────────────────────────────────┘

    ┌─────────────────────────┐
    │  products_service_db    │
    │  Port: 3307             │
    ├─────────────────────────┤
    │  • products             │ ← Catalogue produits principal
    │  • brands               │ ← Fabricants/marques
    │  • types                │ ← Taxonomie + association TVA
    │  • categories           │ ← Hierarchie categorisation
    │  • catalogs             │ ← Collections marketing
    │  • vat                  │ ← Configuration taux TVA
    │  • attributes           │ ← Variantes avec stock
    │  • characteristics      │ ← Proprietes descriptives
    │  • product_images       │ ← Metadonnees images + MinIO
    │  • product_types        │ ← Pivot produit-type
    │  • product_categories   │ ← Pivot produit-categorie
    │  • product_catalogs     │ ← Pivot produit-catalogue
    │  + 3 autres tables      │
    └─────────────────────────┘
              │
              │ Images stockees dans MinIO
              ▼
    ┌─────────────────────────┐
    │  MinIO: products bucket │
    │  Port: 9000 (API)       │
    │  Port: 9001 (Console)   │
    └─────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                 COUCHE PROCESSUS DE COMMANDE                        │
└────────────────────────────────────────────────────────────────────┘

    ┌─────────────────────────┐      ┌─────────────────────────┐
    │  baskets_service_db     │      │  orders_service_db      │
    │  Port: 3319             │      │  Port: 3330             │
    ├─────────────────────────┤      ├─────────────────────────┤
    │  • baskets              │      │  • orders               │
    │  • basket_items         │      │  • order_items          │
    │  • promo_codes          │      │  • order_status         │
    │  • basket_promo_code    │      └─────────────────────────┘
    │  • types                │
    └─────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│              COUCHE ADRESSES ET LIVRAISONS                          │
└────────────────────────────────────────────────────────────────────┘

    ┌─────────────────────────┐      ┌─────────────────────────┐
    │  addresses_service_db   │      │  deliveries_service_db  │
    │  Port: 3318             │      │  Port: 3331             │
    ├─────────────────────────┤      ├─────────────────────────┤
    │  • addresses            │      │  • deliveries           │
    │  • user_addresses       │      │  • delivery_status      │
    └─────────────────────────┘      │  • carriers             │
                                     └─────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│            COUCHE COMMUNICATION ET SUPPORT                          │
└────────────────────────────────────────────────────────────────────┘

    ┌─────────────────────────┐      ┌─────────────────────────┐
    │  newsletters_service_db │      │  sav_service_db         │
    │  Port: 3310             │      │  Port: 3311             │
    ├─────────────────────────┤      ├─────────────────────────┤
    │  • newsletters          │      │  • tickets              │
    │  • newsletter_subscr... │      │  • ticket_status        │
    │  • subscriptions        │      │  • messages             │
    │  • subscription_status  │      │  • ticket_attachments   │
    └─────────────────────────┘      └─────────────────────────┘
                                              │
                                              │ Pieces jointes MinIO
                                              ▼
                                     ┌─────────────────────────┐
                                     │  MinIO: sav bucket      │
                                     └─────────────────────────┘

    ┌─────────────────────────┐
    │  contacts_service_db    │
    │  Port: 3309             │
    ├─────────────────────────┤
    │  • contacts             │
    └─────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│               COUCHE GESTION CONTENU                                │
└────────────────────────────────────────────────────────────────────┘

    ┌─────────────────────────┐      ┌─────────────────────────┐
    │  websites_service_db    │      │  questions_service_db   │
    │  Port: 3312             │      │  Port: 3313             │
    ├─────────────────────────┤      ├─────────────────────────┤
    │  • websites             │      │  • questions            │
    └─────────────────────────┘      └─────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                  COUCHE MESSAGERIE (RabbitMQ)                       │
└────────────────────────────────────────────────────────────────────┘

    ┌──────────────────────────────────────────────────────────┐
    │  RabbitMQ Message Broker                                 │
    │  Port: 5672 (AMQP), 15672 (Management UI)                │
    ├──────────────────────────────────────────────────────────┤
    │  Exchanges (Topic):                                      │
    │    • products_exchange                                   │
    │    • orders_exchange                                     │
    │    • baskets_exchange                                    │
    │    • deliveries_exchange                                 │
    │    • auth_exchange                                       │
    │    • ...                                                 │
    │                                                          │
    │  Queues de Consommateurs:                                │
    │    • [service].events.[target_service]                   │
    │    • Exemple: products.events.baskets                    │
    └──────────────────────────────────────────────────────────┘

LEGENDE:
────────  References virtuelles inter-services (pas de FK)
• Table   Table de base de donnees
```

## Patterns de Communication Inter-Services

### Synchronisation Basee sur les Evenements

**Principe:** Les services communiquent via evenements publies/consommes, et non par appels API directs.

**Flux d'Evenements Exemple:**
```
1. products-service publie: ProductUpdated(product_id, price_ht)
2. baskets-service consomme: Met a jour prix cache dans basket_items
3. orders-service consomme: Synchronise catalogue pour nouvelles commandes
```

### Pattern Base de Donnees par Service

**Avantages:**
- Evolution independante des schemas
- Isolation des pannes (base de donnees en panne = service isole)
- Optimisation specifique au service
- Deployment independant

**Challenges:**
- Pas de contraintes FK inter-services
- Synchronisation via evenements requise
- Coherence eventuelle vs immediate
- Complexity dans les transactions distribuees

### Pattern Saga pour Workflows Complexes

**Cas d'Usage:** Processus de commande (orders-service)

**Etapes de la Saga Commande:**
```
1. Valider Panier (baskets-service)
2. Valider Produits (products-service)
3. Valider Adresses (addresses-service)
4. Creer Commande (orders-service)
5. Reserver Inventaire (products-service)
6. Autoriser Paiement (payment-service)
7. Confirmer Commande (orders-service)
8. Vider Panier (baskets-service)
9. Creer Livraison (deliveries-service)
10. Capturer Paiement (payment-service)
11. Envoyer Email Confirmation (newsletters-service)
```

**Compensation:** Chaque etape possede une action de compensation pour rollback.

## References Inter-Services (Virtuelles)

### Matrice de References

| Service Source | Champ | References → Service Cible | Type |
|----------------|-------|----------------------------|------|
| baskets | user_id | auth-service.users.id | Virtual FK |
| baskets.basket_items | product_id | products-service.products.id | Virtual FK |
| orders | user_id | auth-service.users.id | Virtual FK |
| orders | billing_address_id | addresses-service.addresses.id | Virtual FK |
| orders | shipping_address_id | addresses-service.addresses.id | Virtual FK |
| orders.order_items | product_id | products-service.products.id | Virtual FK |
| deliveries | order_id | orders-service.orders.id | Virtual FK |
| deliveries | carrier_id | Interne | FK Reel |
| addresses.user_addresses | user_id | auth-service.users.id | Virtual FK |
| addresses.user_addresses | address_id | Interne | FK Reel |
| sav.tickets | user_id | auth-service.users.id | Virtual FK |
| newsletters.subscriptions | user_id | auth-service.users.id | Virtual FK |

**Note:** "Virtual FK" = reference maintenue au niveau applicatif, synchronisee via evenements RabbitMQ.

## Gestion Integration MinIO

### Buckets et Propriete des Services

| Bucket MinIO | Service Proprietaire | Type de Contenu | Limite Taille |
|--------------|---------------------|-----------------|---------------|
| products | products-service | Images produits (original, thumbnail, medium) | 10MB/image |
| sav | sav-service | Pieces jointes tickets (docs, captures ecran) | 5MB/fichier |
| newsletters | newsletters-service | Modeles emails, assets (future) | 2MB/fichier |

### Pattern d'Utilisation MinIO

```php
// Via Service MinIO Partage (shared/)
use Shared\Services\MinioService;

$minioService = new MinioService('products');

// Upload image
$result = $minioService->uploadFile($file, 'products/product-123/image.jpg');
// Retourne: ['url' => 'http://minio:9000/products/...']

// URL presignee (acces temporaire)
$presignedUrl = $minioService->getPresignedUrl('products/product-123/image.jpg', 3600);
// Valide 1 heure

// Suppression fichier
$minioService->deleteFile('products/product-123/image.jpg');
```

## Configuration Standards de Base de Donnees

### Connexion MySQL Commune

```bash
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
Engine: InnoDB
Version: MySQL 8.0
```

### Conventions de Nommage

**Tables:**
- snake_case (minuscules avec underscores)
- Noms pluriels pour tables principales (users, products, orders)
- Noms descriptifs pour tables pivot (basket_promo_code)

**Colonnes:**
- snake_case
- Cles primaires: `id` (BIGINT UNSIGNED AUTO_INCREMENT)
- Cles etrangeres: `[table]_id` ou `id_1` si multiples vers meme table
- Timestamps: `created_at`, `updated_at`, `deleted_at` (soft deletes)

**Index:**
- PRIMARY KEY sur id
- INDEX sur cles etrangeres
- UNIQUE sur contraintes metier
- INDEX composes pour requetes frequentes

### Soft Deletes

**Services utilisant Soft Deletes:**
- auth-service (users)
- products-service (products, brands, catalogs)
- baskets-service (baskets, promo_codes, types)
- orders-service (orders)
- addresses-service (addresses)
- deliveries-service (deliveries)
- sav-service (tickets)
- newsletters-service (newsletters, subscriptions)

**Rationale:**
- Preservation historique des donnees
- Recuperation apres suppression accidentelle
- Support analytique et reporting
- Conformite reglementaire (RGPD)

### Strategie de Nettoyage

```sql
-- Exemple: Nettoyer anciens paniers soft-deleted (90 jours)
DELETE FROM baskets
WHERE deleted_at IS NOT NULL
  AND deleted_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Exemple: Nettoyer paniers abandonnes (30 jours)
DELETE FROM baskets
WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
  AND deleted_at IS NULL;
```

## Stack Technologique

**Framework & Langage:**
- PHP 8.3+
- Laravel 12
- Composer gestion dependances

**Bases de Donnees:**
- MySQL 8.0 (12 bases isolees)
- Redis (cache sessions, future)

**Message Queue:**
- RabbitMQ 3.x
- php-amqplib pour integration

**Stockage Objets:**
- MinIO (compatible S3)
- AWS SDK pour operations

**Authentification:**
- JWT via tymon/jwt-auth
- RBAC via Spatie Laravel Permission

**Tests & Qualite:**
- PHPUnit (tests unitaires/features)
- Laravel Pint (formatage code)
- Collections Postman API

## Recommandations de Performance

### Indexation
1. **Cles Primaires:** Toujours BIGINT UNSIGNED AUTO_INCREMENT
2. **Cles Etrangeres:** Index sur toutes FK (meme virtuelles)
3. **Requetes Frequentes:** Index composes pour patterns courants
4. **Recherche Texte:** INDEX sur champs recherchables (name, ref, email)

### Caching
1. **Donnees Reference:** Cache taux TVA, statuts, roles
2. **Recherches Produits:** Cache resultats recherche populaires
3. **Sessions Utilisateur:** Redis pour scalabilite
4. **Donnees Calculees:** Cache totaux paniers, statistiques

### Optimisation Requetes
1. **Eager Loading:** Utiliser `->with()` pour eviter N+1
2. **Pagination:** Toujours limiter resultats (LIMIT/OFFSET)
3. **Indexes:** EXPLAIN ANALYZE pour valider utilisation index
4. **Soft Deletes:** Toujours `WHERE deleted_at IS NULL` dans queries

### Surveillance
1. **Slow Query Log:** Identifier requetes > 1s
2. **Metriques Connexions:** Pool connections, temps attente
3. **Taille Tables:** Surveiller croissance, archiver donnees anciennes
4. **Metriques RabbitMQ:** Profondeur files, taux consommation

## Liens vers Documentation Detaillee

### Documentation Services
- [Auth Service Database](services/auth-service-database.md)
- [Products Service Database](services/products-service-database.md)
- [Baskets Service Database](services/baskets-service-database.md)
- [Orders Service Database](services/orders-service-database.md)
- [Deliveries Service Database](services/deliveries-service-database.md)
- [Addresses Service Database](services/addresses-service-database.md)
- [SAV Service Database](services/sav-service-database.md)
- [Newsletters Service Database](services/newsletters-service-database.md)
- [Contacts Service Database](services/contacts-service-database.md)
- [Websites Service Database](services/websites-service-database.md)
- [Questions Service Database](services/questions-service-database.md)

### Documentation Generale
- [Database Relationships](01-database-relationships.md)
- [MinIO Integration](../minio/MINIO.md)
- [RabbitMQ Messaging](../messaging/README.md)

---

**Version Document:** 1.0
**Derniere Mise a Jour:** 2025-10-03
**Version Base de Donnees:** MySQL 8.0
**Version Laravel:** 12.x
