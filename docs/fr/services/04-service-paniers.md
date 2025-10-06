# Service Paniers

## Presentation du Service

**Objectif** : Gestion panier d'achat incluant articles panier, codes promo et calculs panier.

**Port** : 8002
**Base de donnees** : baskets_db (MySQL 8.0)
**Port Externe** : 3309 (pour debogage)
**Dependances** : Service Auth (validation JWT), Service Produits (details produits, tarification, stock)

## Responsabilites

- Gestion cycle de vie panier d'achat
- Gestion articles panier (ajouter, modifier, retirer)
- Application et validation codes promotionnels
- Calculs prix (sous-total, remises, totaux)
- Persistence panier entre sessions
- Gestion type panier (standard, sauvegarde, abandonne)
- Integration avec service produits pour tarification temps reel

## Points de Terminaison API

### Points de Terminaison Publics

| Methode | Point de Terminaison | Description | Auth Requis | Requete/Reponse |
|---------|----------------------|-------------|-------------|-----------------|
| GET | /health | Verification sante service | Non | {"status":"healthy","service":"baskets-service"} |
| POST | /promo-codes/validate | Valider code promo | Non | {"code"} -> {"valid":true,"discount":{}} |

### Points de Terminaison Utilisateur (Auth Requis)

| Methode | Point de Terminaison | Description | Requete/Reponse |
|---------|----------------------|-------------|-----------------|
| GET | /baskets/current | Obtenir panier actuel utilisateur | Panier avec articles et totaux |
| POST | /baskets/items | Ajouter article au panier | {"product_id","quantity","options"} -> Panier mis a jour |
| PUT | /baskets/items/{id} | Mettre a jour article panier | {"quantity","options"} -> Panier mis a jour |
| DELETE | /baskets/items/{id} | Retirer article panier | Message succes |
| POST | /baskets/promo-codes | Appliquer code promo | {"code"} -> Panier mis a jour avec remise |
| DELETE | /baskets/promo-codes/{id} | Retirer code promo | Panier mis a jour |
| DELETE | /baskets/clear | Vider panier entier | Message succes |

### Points de Terminaison Admin (Auth Requis + Role Admin)

| Methode | Point de Terminaison | Description | Requete/Reponse |
|---------|----------------------|-------------|-----------------|
| GET | /admin/baskets | Lister tous paniers | Liste paniers paginee avec filtres |
| GET | /admin/baskets/{id} | Obtenir details panier | Objet panier complet |
| DELETE | /admin/baskets/{id} | Supprimer panier | Message succes |
| GET | /admin/promo-codes | Lister codes promo | [{"code","type","value","usage"}] |
| POST | /admin/promo-codes | Creer code promo | Donnees code promo -> Code cree |
| GET | /admin/promo-codes/{id} | Obtenir details code promo | Code promo avec stats utilisation |
| PUT | /admin/promo-codes/{id} | Mettre a jour code promo | Code promo mis a jour |
| DELETE | /admin/promo-codes/{id} | Supprimer code promo | Message succes |
| GET | /admin/types | Lister types panier | Liste types |
| POST | /admin/types | Creer type panier | Donnees type -> Type cree |
| GET | /admin/types/{id} | Obtenir details type | Objet type |
| PUT | /admin/types/{id} | Mettre a jour type | Type mis a jour |
| DELETE | /admin/types/{id} | Supprimer type | Message succes |

## Schema de Base de Donnees

**Tables** :

1. **baskets** - Instances panier d'achat
   - id (PK)
   - user_id (FK, nullable pour paniers invites)
   - session_id (pour paniers invites)
   - type_id (FK - actif, sauvegarde, abandonne)
   - subtotal (decimal)
   - discount_amount (decimal)
   - tax_amount (decimal)
   - total (decimal)
   - currency (defaut : USD)
   - ip_address
   - user_agent
   - converted_to_order_id (FK, nullable)
   - abandoned_at (timestamp, nullable)
   - timestamps

2. **basket_items** - Articles dans paniers
   - id (PK)
   - basket_id (FK)
   - product_id (FK - reference depuis service produits)
   - quantity (integer)
   - unit_price (decimal - instantane au moment ajout)
   - total_price (decimal - calcule)
   - product_snapshot (JSON - details produit au moment ajout)
   - options (JSON - taille, couleur, etc.)
   - timestamps

3. **promo_codes** - Codes reduction promotionnels
   - id (PK)
   - code (unique)
   - description
   - type (enum : percentage, fixed_amount, free_shipping)
   - value (decimal - pourcentage ou montant)
   - minimum_purchase (decimal, nullable)
   - maximum_discount (decimal, nullable)
   - usage_limit (integer, nullable)
   - usage_count (integer, defaut 0)
   - per_user_limit (integer, nullable)
   - start_date (timestamp)
   - end_date (timestamp, nullable)
   - is_active (boolean)
   - applicable_products (JSON - IDs produits, nullable)
   - applicable_categories (JSON - IDs categories, nullable)
   - timestamps

4. **basket_promo_code** - Codes promo appliques (plusieurs-a-plusieurs)
   - id (PK)
   - basket_id (FK)
   - promo_code_id (FK)
   - discount_amount (decimal - remise calculee)
   - applied_at (timestamp)

5. **types** - Types/etats panier
   - id (PK)
   - name (active, saved, abandoned, completed)
   - description
   - timestamps

**Relations** :
- Basket -> User (appartient a, nullable)
- Basket -> Type (appartient a)
- Basket -> Items (a plusieurs)
- Basket -> PromoCodes (plusieurs-a-plusieurs)
- BasketItem -> Product (reference via product_id)

## Calculs Panier

**Flux Calcul Prix** :
1. Recuperer prix produits actuels depuis Service Produits
2. Calculer sous-totaux articles : quantite * prix_unitaire
3. Sommer tous sous-totaux articles = sous-total panier
4. Appliquer codes promo (dans ordre application)
5. Calculer taxe selon adresse livraison (futur)
6. Calculer total final : sous-total - remises + taxe

**Regles Application Code Promo** :
- Codes appliques dans ordre ajout
- Codes cumulables (configurable par code)
- Exigences achat minimum verifiees
- Plafonds remise maximum appliques
- Limites utilisation par utilisateur suivies
- Validation plage dates
- Applicabilite produit/categorie verifiee

## Integration RabbitMQ

**Evenements Consommes** :
- `product.price.changed` - Mettre a jour prix articles panier
- `product.deleted` - Retirer produit des paniers
- `product.out_of_stock` - Marquer article comme indisponible
- `order.created` - Marquer panier comme converti

**Evenements Publies** :
- `basket.created` - Nouveau panier cree (pour analytique)
- `basket.item.added` - Article ajoute au panier
- `basket.item.removed` - Article retire du panier
- `basket.abandoned` - Panier inactif depuis 24 heures (pour remarketing)
- `basket.converted` - Panier converti en commande
- `promo_code.applied` - Code promo utilise (pour suivi)

**Exemple Format Message** :
```json
{
  "event": "basket.item.added",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "basket_id": 456,
    "user_id": 123,
    "product_id": 789,
    "quantity": 2,
    "unit_price": 29.99,
    "total_price": 59.98
  }
}
```

## Integration avec Service Produits

**Sync Information Produit** :
- Recuperer details produit lors ajout au panier
- Stocker instantane produit dans basket_items.product_snapshot
- Valider disponibilite produit avant ajout
- Verifier niveaux stock avant paiement
- Mettre a jour prix si change depuis derniere sync

**Format Instantane Produit** :
```json
{
  "product_id": 789,
  "name": "Nom Produit",
  "sku": "PROD-001",
  "description": "Description produit",
  "image_url": "https://minio/products/789/image.jpg",
  "original_price": 29.99,
  "current_price": 24.99,
  "snapshot_at": "2024-01-15T10:30:00Z"
}
```

## Cycle de Vie Panier

**Etats** :
1. **Active** - Session achat actuelle
2. **Saved** - Utilisateur a sauvegarde panier pour plus tard
3. **Abandoned** - Inactif depuis 24+ heures
4. **Converted** - Converti avec succes en commande

**Transitions Etat** :
- Active -> Saved : Utilisateur clique "Sauvegarder pour plus tard"
- Active -> Abandoned : Pas d'activite depuis 24 heures
- Active -> Converted : Commande passee avec succes
- Saved -> Active : Utilisateur reprend achats
- Abandoned -> Active : Utilisateur retourne au panier

**Nettoyage Automatise** :
- Paniers invites : Supprimer apres 30 jours
- Paniers abandonnes : Archiver apres 90 jours
- Paniers convertis : Conserver 1 an pour analyse

## Variables d'Environnement

```bash
# Application
APP_NAME=baskets-service
APP_ENV=local
APP_PORT=8002

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=baskets-mysql
DB_PORT=3306
DB_DATABASE=baskets_db
DB_USERNAME=baskets_user
DB_PASSWORD=baskets_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=baskets_exchange
RABBITMQ_QUEUE=baskets_queue

# URLs Services
AUTH_SERVICE_URL=http://auth-service:8000
PRODUCTS_SERVICE_URL=http://products-service:8001

# Configuration Panier
BASKET_ABANDONMENT_HOURS=24
BASKET_CLEANUP_DAYS=30
MAX_BASKET_ITEMS=50
```

## Deploiement

**Configuration Docker** :
```yaml
Service: baskets-service
Port Mapping: 8002:8000
Database: baskets-mysql (port 3309 externe)
Depends On: baskets-mysql, rabbitmq, auth-service, products-service
Networks: e-commerce-network
Health Check: /health endpoint
```

**Ressources Kubernetes** :
- Deployment : 2 replicas minimum
- CPU Request : 100m, Limit : 300m
- Memory Request : 256Mi, Limit : 512Mi
- Service Type : ClusterIP
- ConfigMap : Configuration panier
- CronJob : Detection paniers abandonnes (quotidien)

**Configuration Health Check** :
- Liveness Probe : GET /health (intervalle 10s)
- Readiness Probe : Connectivite base de donnees
- Startup Probe : timeout 30s

## Optimisation Performance

**Strategie Mise en Cache** :
- Panier actif mis en cache dans Redis (cle user_id)
- Resultats validation code promo mis en cache (TTL 5 min)
- Instantanes prix produits mis en cache (TTL 2 min)
- Calculs panier mis en cache jusqu'a changement article

**Optimisation Base de Donnees** :
- Index sur : user_id, session_id, type_id, abandoned_at
- Index composite pour recherches articles panier
- Suppressions douces pour paniers (conserver historique)

**Jobs Planifies** :
- Detecteur paniers abandonnes (execute toutes les heures)
- Nettoyage paniers invites (execute quotidiennement a 2h)
- Sync prix avec service produits (execute toutes les 30 min)

## Considerations de Securite

**Controle Acces Panier** :
- Utilisateurs peuvent uniquement acceder leurs propres paniers
- Acces base session pour paniers invites
- Role admin requis pour gestion paniers
- Validation code promo previent attaques enumeration

**Validation Entrees** :
- Limites quantite (1-99 par article)
- Maximum articles panier (50 articles)
- Validation format code promo
- Protection injection SQL (Eloquent ORM)

## Surveillance et Observabilite

**Metriques a Suivre** :
- Taille moyenne panier (articles)
- Valeur moyenne panier
- Taux abandon panier
- Taux utilisation code promo
- Taux conversion (panier -> commande)
- Echecs sync prix

**Journalisation** :
- Creation et conversion panier
- Ajouts et retraits articles
- Applications codes promo
- Evenements paniers abandonnes
- Operations sync prix

## Ameliorations Futures

- Fusion panier invite vers panier utilisateur a connexion
- Fonctionnalite partage panier
- Conversion liste souhaits vers panier
- Paniers sauvegardes avec noms
- Recommandations panier (complete le look)
- Suggestions vente croisee et montee gamme
- Indicateurs disponibilite stock temps reel
- Support multi-devises
- Options emballage cadeau
- Support panier abonnement
