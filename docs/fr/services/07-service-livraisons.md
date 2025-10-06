# Service Livraisons

## Presentation du Service

**Objectif** : Suivi livraisons, gestion expeditions et gestion points de vente (lieux retrait).

**Port** : 8005
**Base de donnees** : deliveries_db (MySQL 8.0)
**Port Externe** : 3312 (pour debogage)
**Dependances** : Service Auth, Service Commandes (traitement commandes), Service Adresses (adresses livraison)

## Responsabilites

- Gestion cycle de vie livraison
- Suivi expeditions
- Coordination integration transporteurs
- Gestion points de vente (lieux retrait)
- Suivi statut livraison
- Dates livraison estimees
- Mises a jour suivi temps reel
- Statistiques et analytique livraisons

## Points de Terminaison API

### Points de Terminaison Publics

| Methode | Point de Terminaison | Description | Auth Requis | Requete/Reponse |
|---------|----------------------|-------------|-------------|-----------------|
| GET | /health | Verification sante service | Non | {"status":"healthy","service":"deliveries-service"} |
| GET | /debug | Point terminaison debug | Non | "Deliveries service debug endpoint" |
| GET | /deliveries/track/{trackingNumber} | Suivi public | Non | Statut livraison et historique |
| GET | /sale-points | Lister points de vente | Non | [{"id","name","address","hours"}] |
| GET | /sale-points/{id} | Details point de vente | Non | Objet point vente complet |
| GET | /sale-points/nearby | Trouver lieux a proximite | Non | Params requete : lat, lng, radius |
| GET | /status | Lister statuts livraison | Non | [{"id","name","description"}] |
| GET | /status/{id} | Details statut | Non | Objet statut |
| GET | /status/statistics | Distribution statut | Non | Statistiques livraisons par statut |

### Points de Terminaison Utilisateur (Auth Requis)

| Methode | Point de Terminaison | Description | Requete/Reponse |
|---------|----------------------|-------------|-----------------|
| GET | /deliveries | Lister livraisons utilisateur | Liste livraisons paginee |
| GET | /deliveries/{id} | Obtenir details livraison | Livraison complete avec historique suivi |
| PUT | /deliveries/{id}/status | Mettre a jour statut livraison | {"status_id"} -> Livraison mise a jour |
| POST | /deliveries/from-order | Creer depuis commande | {"order_id"} -> Livraison creee |

### Points de Terminaison Admin (Auth Requis + Role Admin)

| Methode | Point de Terminaison | Description | Requete/Reponse |
|---------|----------------------|-------------|-----------------|
| GET | /admin/deliveries | Lister toutes livraisons | Paginee avec filtres |
| GET | /admin/deliveries/statistics | Analytique livraisons | Donnees tableau de bord statistiques |
| POST | /admin/deliveries | Creer livraison | Donnees livraison -> Livraison creee |
| GET | /admin/deliveries/{id} | Obtenir details livraison | Objet livraison complet |
| PUT | /admin/deliveries/{id} | Mettre a jour livraison | Livraison mise a jour |
| DELETE | /admin/deliveries/{id} | Supprimer livraison | Message succes |
| GET | /admin/sale-points | Gerer points de vente | Liste points vente avec stats |
| GET | /admin/sale-points/statistics | Analytique points vente | Statistiques utilisation |
| POST | /admin/sale-points | Creer point de vente | Donnees point vente -> Lieu cree |
| GET | /admin/sale-points/{id} | Details point de vente | Objet point vente complet |
| PUT | /admin/sale-points/{id} | Mettre a jour point de vente | Point vente mis a jour |
| DELETE | /admin/sale-points/{id} | Supprimer point de vente | Message succes |
| GET | /admin/status | Gerer statuts | Liste statuts |
| GET | /admin/status/statistics | Analytique statuts | Donnees distribution |
| POST | /admin/status | Creer statut | Donnees statut -> Statut cree |
| GET | /admin/status/{id} | Details statut | Objet statut |
| PUT | /admin/status/{id} | Mettre a jour statut | Statut mis a jour |
| DELETE | /admin/status/{id} | Supprimer statut | Message succes |

## Schema de Base de Donnees

**Tables** :

1. **deliveries** - Enregistrements expedition
   - id (PK)
   - order_id (FK - reference depuis service commandes)
   - user_id (FK)
   - tracking_number (unique, genere)
   - carrier_name (string - UPS, FedEx, USPS, DHL, etc.)
   - carrier_tracking_number (nullable - ID suivi externe)
   - status_id (FK)
   - shipping_method (enum : standard, express, overnight, pickup)
   - sale_point_id (FK, nullable - pour livraisons retrait)
   - shipping_address_id (FK - reference depuis service adresses)
   - weight (decimal, nullable)
   - dimensions (JSON, nullable)
   - shipped_at (timestamp, nullable)
   - estimated_delivery_at (timestamp, nullable)
   - delivered_at (timestamp, nullable)
   - delivery_signature (string, nullable)
   - delivery_notes (text, nullable)
   - tracking_events (JSON - historique suivi)
   - timestamps

2. **sale_points** - Lieux retrait
   - id (PK)
   - name
   - code (unique)
   - type (enum : store, locker, partner)
   - address_line_1
   - address_line_2 (nullable)
   - city
   - region
   - postal_code
   - country
   - latitude (decimal)
   - longitude (decimal)
   - phone
   - email
   - opening_hours (JSON - planning)
   - capacity (integer - max colis)
   - current_load (integer - colis actuels)
   - is_active (boolean)
   - features (JSON - has_parking, wheelchair_accessible, etc.)
   - timestamps

3. **status** - Etats statut livraison
   - id (PK)
   - name (pending, processing, in_transit, out_for_delivery, delivered, failed, returned)
   - description
   - color (pour UI)
   - order (integer - ordre affichage)
   - is_final (boolean)
   - timestamps

**Relations** :
- Delivery -> Order (appartient a, reference)
- Delivery -> User (appartient a)
- Delivery -> Status (appartient a)
- Delivery -> SalePoint (appartient a, nullable)

## Flux Statut Livraison

**Progression Statut** :
```
pending -> processing -> in_transit -> out_for_delivery -> delivered
                            |
                            +-> failed -> returned
```

**Definitions Statut** :
- **pending** : Livraison creee, attente enlevement
- **processing** : Colis en preparation
- **in_transit** : Colis dans reseau transporteur
- **out_for_delivery** : Sorti avec coursier pour livraison
- **delivered** : Livre avec succes
- **failed** : Tentative livraison echouee
- **returned** : Retourne a expediteur

## Historique Evenements Suivi

**Structure Evenements Suivi** :
```json
[
  {
    "timestamp": "2024-01-15T10:00:00Z",
    "status": "in_transit",
    "location": "Centre Distribution - Ville",
    "description": "Colis arrive au centre de distribution",
    "carrier_event_code": "AR"
  },
  {
    "timestamp": "2024-01-15T14:30:00Z",
    "status": "out_for_delivery",
    "location": "Installation Locale - Ville",
    "description": "Sorti pour livraison",
    "carrier_event_code": "OFD"
  }
]
```

## Fonctionnalites Points de Vente

**Recherche Lieux a Proximite** :
- Recherche basee geolocalisation (rayon en km)
- Filtrer par type, fonctionnalites, capacite
- Trier par distance depuis localisation utilisateur
- Information capacite temps reel

**Format Horaires Ouverture** :
```json
{
  "monday": {"open": "09:00", "close": "18:00"},
  "tuesday": {"open": "09:00", "close": "18:00"},
  "wednesday": {"open": "09:00", "close": "18:00"},
  "thursday": {"open": "09:00", "close": "18:00"},
  "friday": {"open": "09:00", "close": "20:00"},
  "saturday": {"open": "10:00", "close": "17:00"},
  "sunday": "closed"
}
```

## Integration RabbitMQ

**Evenements Consommes** :
- `order.confirmed` - Creer livraison depuis commande
- `order.cancelled` - Annuler livraison associee
- `carrier.tracking.update` - Webhook suivi externe

**Evenements Publies** :
- `delivery.created` - Nouvelle livraison creee
- `delivery.status.changed` - Statut mis a jour
- `delivery.shipped` - Colis expedie (vers service commandes)
- `delivery.delivered` - Colis livre (vers service commandes)
- `delivery.failed` - Tentative livraison echouee
- `sale_point.capacity.warning` - Point vente proche capacite

**Exemple Format Message** :
```json
{
  "event": "delivery.shipped",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "delivery_id": 789,
    "order_id": 456,
    "tracking_number": "DEL-20240115-789",
    "carrier": "UPS",
    "carrier_tracking_number": "1Z999AA10123456784",
    "estimated_delivery": "2024-01-18T17:00:00Z"
  }
}
```

## Integration Transporteurs

**Transporteurs Supportes** (Points Integration) :
- UPS
- FedEx
- USPS
- DHL
- Services coursier locaux

**Integration API Transporteur** (Futur) :
- Devis tarifs temps reel
- Generation etiquettes
- Mises a jour suivi automatiques
- Confirmation livraison
- Validation adresse

## Variables d'Environnement

```bash
# Application
APP_NAME=deliveries-service
APP_ENV=local
APP_PORT=8005

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=deliveries-mysql
DB_PORT=3306
DB_DATABASE=deliveries_db
DB_USERNAME=deliveries_user
DB_PASSWORD=deliveries_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=deliveries_exchange
RABBITMQ_QUEUE=deliveries_queue

# URLs Services
AUTH_SERVICE_URL=http://auth-service:8000
ORDERS_SERVICE_URL=http://orders-service:8003
ADDRESSES_SERVICE_URL=http://addresses-service:8004

# Configuration Livraison
TRACKING_NUMBER_PREFIX=DEL
DEFAULT_CARRIER=UPS
ESTIMATED_DELIVERY_DAYS=3
```

## Deploiement

**Configuration Docker** :
```yaml
Service: deliveries-service
Port Mapping: 8005:8000
Database: deliveries-mysql (port 3312 externe)
Depends On: deliveries-mysql, rabbitmq, orders-service
Networks: e-commerce-network
Health Check: /health endpoint
```

**Ressources Kubernetes** :
- Deployment : 2 replicas
- CPU Request : 100m, Limit : 300m
- Memory Request : 256Mi, Limit : 512Mi
- Service Type : ClusterIP
- ConfigMap : Configuration transporteur

**Configuration Health Check** :
- Liveness Probe : GET /health (intervalle 10s)
- Readiness Probe : Connectivite base de donnees
- Startup Probe : timeout 30s

## Optimisation Performance

**Strategie Mise en Cache** :
- Liste points vente mise en cache (TTL 10 min)
- Liste statuts livraison mise en cache (n'expire jamais)
- Historique livraisons utilisateur mis en cache (TTL 3 min)
- Points vente a proximite mis en cache par localisation (TTL 5 min)

**Optimisation Base de Donnees** :
- Index sur : tracking_number, order_id, user_id, status_id
- Index geospatial sur sale_points (latitude, longitude)
- Partitionnement par date livraison pour donnees historiques

**Jobs Planifies** :
- Sync suivi transporteur (toutes les 30 min)
- Mises a jour capacite points vente (toutes les heures)
- Recalcul livraison estimee (quotidien)

## Surveillance et Observabilite

**Metriques a Suivre** :
- Livraisons par statut
- Temps livraison moyen
- Taux livraison a temps
- Taux livraison echouee
- Utilisation points vente
- Performance transporteur

**Journalisation** :
- Creation livraison et changements statut
- Mises a jour suivi
- Alertes capacite points vente
- Echecs API transporteur

## Ameliorations Futures

- Suivi GPS temps reel pour coursiers
- Selection creneaux livraison par client
- Notifications livraison SMS/email
- Photos preuve livraison
- Planification re-livraison
- Generation etiquettes retour
- Expeditions multi-colis
- Support expedition internationale
- Documentation douaniere
- Integration assurance expedition
