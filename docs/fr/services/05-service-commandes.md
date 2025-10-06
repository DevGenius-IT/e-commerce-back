# Service Commandes

## Presentation du Service

**Objectif** : Traitement des commandes et gestion du cycle de vie avec modele machine a etats pour transitions statut commande.

**Port** : 8003
**Base de donnees** : orders_db (MySQL 8.0)
**Port Externe** : 3310 (pour debogage)
**Dependances** : Service Auth, Service Paniers (conversion panier), Service Produits (inventaire), Service Livraisons (expedition)

## Responsabilites

- Creation commande depuis conversion panier
- Gestion cycle de vie commande (machine a etats)
- Suivi statut commande et transitions
- Gestion articles commande
- Historique et suivi commandes
- Suivi statut paiement (point integration)
- Gestion annulations et remboursements
- Statistiques et rapports commandes

## Points de Terminaison API

### Points de Terminaison Publics

| Methode | Point de Terminaison | Description | Auth Requis | Requete/Reponse |
|---------|----------------------|-------------|-------------|-----------------|
| GET | /health | Verification sante service | Non | {"status":"healthy","service":"orders-service"} |
| GET | /debug | Point terminaison debug simple | Non | "Simple debug text" |
| GET | /order-status | Lister statuts commande | Non | [{"id","name","description"}] |
| GET | /order-status/{id} | Obtenir details statut | Non | Objet statut |
| GET | /order-status/statistics | Statistiques statut | Non | Donnees distribution statut |

### Points de Terminaison Utilisateur (Auth Requis - Temporairement Public pour Tests)

| Methode | Point de Terminaison | Description | Requete/Reponse |
|---------|----------------------|-------------|-----------------|
| GET | /orders | Lister commandes utilisateur | Liste commandes paginee |
| GET | /orders/{id} | Obtenir details commande | Commande complete avec articles et statut |
| POST | /orders/create-from-basket | Creer commande depuis panier | {"basket_id","shipping_address_id","billing_address_id"} -> Commande creee |
| PUT | /orders/{id}/status | Mettre a jour statut commande | {"status_id"} -> Commande mise a jour |
| PUT | /orders/{id}/cancel | Annuler commande | Raison annulation -> Succes |

### Points de Terminaison Admin (Temporairement Public pour Tests)

| Methode | Point de Terminaison | Description | Requete/Reponse |
|---------|----------------------|-------------|-----------------|
| GET | /admin/orders | Lister toutes commandes | Paginee avec filtres (statut, plage dates, utilisateur) |
| GET | /admin/orders/{id} | Obtenir details commande | Objet commande complet |
| PUT | /admin/orders/{id} | Mettre a jour commande | Donnees commande -> Commande mise a jour |
| DELETE | /admin/orders/{id} | Supprimer commande | Message succes (suppression douce) |
| POST | /admin/order-status | Creer statut commande | Donnees statut -> Statut cree |
| PUT | /admin/order-status/{id} | Mettre a jour statut | Statut mis a jour |
| DELETE | /admin/order-status/{id} | Supprimer statut | Message succes |

## Schema de Base de Donnees

**Tables** :

1. **orders** - En-tetes commande
   - id (PK)
   - order_number (unique, genere)
   - user_id (FK)
   - basket_id (FK, nullable)
   - status_id (FK)
   - subtotal (decimal)
   - discount_amount (decimal)
   - tax_amount (decimal)
   - shipping_amount (decimal)
   - total (decimal)
   - currency (defaut : USD)
   - payment_method
   - payment_status (enum : pending, authorized, paid, failed, refunded)
   - payment_transaction_id
   - shipping_address_id (FK vers service adresses)
   - billing_address_id (FK vers service adresses)
   - customer_notes (text)
   - admin_notes (text)
   - ip_address
   - user_agent
   - cancelled_at (timestamp, nullable)
   - cancellation_reason (text, nullable)
   - timestamps, soft_deletes

2. **order_items** - Lignes articles dans commandes
   - id (PK)
   - order_id (FK)
   - product_id (FK - reference depuis service produits)
   - quantity (integer)
   - unit_price (decimal - instantane au moment commande)
   - total_price (decimal - calcule)
   - tax_amount (decimal)
   - discount_amount (decimal)
   - product_snapshot (JSON - details produit au moment commande)
   - timestamps

3. **order_status** - Etats commande
   - id (PK)
   - name (pending, processing, confirmed, shipped, delivered, cancelled, refunded)
   - description
   - color (pour affichage UI)
   - order (integer - ordre affichage)
   - is_cancellable (boolean)
   - is_final (boolean)
   - timestamps

**Relations** :
- Order -> User (appartient a)
- Order -> Basket (appartient a, nullable)
- Order -> Status (appartient a)
- Order -> Items (a plusieurs)
- OrderItem -> Product (reference via product_id)

## Machine a Etats Statut Commande

**Flux Statut** :
```
pending -> processing -> confirmed -> shipped -> delivered
   |
   +-> cancelled
   |
   +-> refunded (depuis tout statut pre-livraison)
```

**Definitions Statut** :
- **pending** : Commande passee, attente confirmation paiement
- **processing** : Paiement confirme, preparation commande
- **confirmed** : Commande confirmee, prete expedition
- **shipped** : Commande expediee, en transit
- **delivered** : Commande livree avec succes
- **cancelled** : Commande annulee (par client ou admin)
- **refunded** : Paiement rembourse

**Regles Transition** :
- Impossible annuler apres statut shipped
- Impossible modifier commande apres debut processing
- Remboursement autorise uniquement avant livraison
- Transitions statut journalisees pour piste audit

## Flux Creation Commande

1. Utilisateur initie paiement depuis panier
2. Service Paniers valide panier (disponibilite stock, prix)
3. Service Commandes cree commande depuis panier
4. Instantanes produits stockes dans order_items
5. Service Produits reduit inventaire
6. Statut commande defini a "pending"
7. Integration passerelle paiement (futur)
8. Sur succes paiement : statut -> "processing"
9. Service Livraisons notifie pour creer expedition
10. Confirmation commande envoyee a utilisateur

## Integration RabbitMQ

**Evenements Consommes** :
- `basket.checkout.request` - Panier pret pour conversion
- `payment.authorized` - Paiement reussi
- `payment.failed` - Paiement echoue
- `delivery.shipped` - Commande expediee
- `delivery.delivered` - Commande livree

**Evenements Publies** :
- `order.created` - Nouvelle commande passee
- `order.status.changed` - Transition statut
- `order.cancelled` - Annulation commande
- `order.refunded` - Remboursement traite
- `inventory.reserve` - Reserver produits (vers service produits)
- `inventory.release` - Liberer produits reserves (a annulation)
- `delivery.create.request` - Creer expedition (vers service livraisons)

**Exemple Format Message** :
```json
{
  "event": "order.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "order_id": 1001,
    "order_number": "ORD-20240115-1001",
    "user_id": 123,
    "total": 149.98,
    "items": [
      {"product_id": 789, "quantity": 2, "unit_price": 49.99},
      {"product_id": 790, "quantity": 1, "unit_price": 49.99}
    ],
    "shipping_address_id": 456,
    "billing_address_id": 457
  }
}
```

## Variables d'Environnement

```bash
# Application
APP_NAME=orders-service
APP_ENV=local
APP_PORT=8003

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=orders-mysql
DB_PORT=3306
DB_DATABASE=orders_db
DB_USERNAME=orders_user
DB_PASSWORD=orders_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=orders_exchange
RABBITMQ_QUEUE=orders_queue

# URLs Services
AUTH_SERVICE_URL=http://auth-service:8000
BASKETS_SERVICE_URL=http://baskets-service:8002
PRODUCTS_SERVICE_URL=http://products-service:8001
DELIVERIES_SERVICE_URL=http://deliveries-service:8005

# Configuration Commande
ORDER_NUMBER_PREFIX=ORD
ORDER_CANCELLATION_WINDOW_HOURS=24
```

## Deploiement

**Configuration Docker** :
```yaml
Service: orders-service
Port Mapping: 8003:8000
Database: orders-mysql (port 3310 externe)
Depends On: orders-mysql, rabbitmq, baskets-service, products-service
Networks: e-commerce-network
Health Check: /health endpoint
```

**Ressources Kubernetes** :
- Deployment : 3 replicas minimum (haute disponibilite)
- CPU Request : 200m, Limit : 500m
- Memory Request : 512Mi, Limit : 1Gi
- Service Type : ClusterIP
- ConfigMap : Configuration commande
- Secret : Identifiants passerelle paiement (futur)

**Configuration Health Check** :
- Liveness Probe : GET /health (intervalle 10s)
- Readiness Probe : Connectivite base de donnees
- Startup Probe : timeout 30s

## Optimisation Performance

**Strategie Mise en Cache** :
- Liste statuts commande mise en cache (change rarement)
- Historique commandes utilisateur mis en cache (TTL 5 min)
- Details commande mis en cache (TTL 2 min)
- Statistiques mises en cache (TTL 15 min)

**Optimisation Base de Donnees** :
- Index sur : order_number, user_id, status_id, created_at
- Index composites pour requetes filtrage
- Partitionnement par date pour commandes historiques
- Archiver anciennes commandes (1+ an) vers table separee

## Considerations de Securite

**Controle Acces** :
- Utilisateurs peuvent uniquement voir leurs propres commandes
- Role admin requis pour acces toutes commandes
- Modification commande restreinte apres processing
- Fenetre annulation appliquee

**Protection Donnees** :
- Donnees paiement sensibles non stockees
- Conformite PCI pour integration passerelle paiement
- Politique retention historique commandes
- Conformite RGPD pour suppression donnees

## Surveillance et Observabilite

**Metriques a Suivre** :
- Taux creation commande
- Valeur moyenne commande
- Distribution statut commande
- Taux annulation
- Temps dans chaque statut
- Taux succes paiement

**Journalisation** :
- Toutes creations commandes
- Transitions statut
- Annulations et remboursements
- Evenements paiement
- Echecs integration

## Ameliorations Futures

- Integration passerelle paiement (Stripe, PayPal)
- Support paiements fractionnes
- Annulations/remboursements partiels
- Modification commande apres placement
- Commandes abonnement
- Traitement produits numeriques
- Notifications suivi commande
- Generation facture (PDF)
- Systeme gestion retours
- Integration points fidelite
