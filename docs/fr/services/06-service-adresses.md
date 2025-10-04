# Service Adresses

## Presentation du Service

**Objectif** : Gestion et validation adresses pour adresses livraison et facturation.

**Port** : 8004
**Base de donnees** : addresses_db (MySQL 8.0)
**Port Externe** : 3311 (pour debogage)
**Dependances** : Service Auth (authentification utilisateur)

## Responsabilites

- Operations CRUD adresses
- Gestion adresses livraison et facturation
- Selection adresse par defaut
- Validation adresse
- Donnees reference pays et regions
- Classification type adresse (livraison, facturation, les deux)

## Points de Terminaison API

### Points de Terminaison Publics

| Methode | Point de Terminaison | Description | Auth Requis | Requete/Reponse |
|---------|----------------------|-------------|-------------|-----------------|
| GET | /health | Verification sante service | Non | {"status":"healthy","service":"addresses-service"} |
| GET | /countries | Lister pays | Non | [{"id","name","code","regions":[]}] |
| GET | /countries/{id} | Obtenir details pays | Non | Objet pays |
| GET | /countries/{id}/regions | Obtenir regions pays | Non | [{"id","name","code"}] |

### Points de Terminaison Utilisateur (Auth Requis via RabbitMQ)

| Methode | Point de Terminaison | Description | Requete/Reponse |
|---------|----------------------|-------------|-----------------|
| GET | /addresses | Lister adresses utilisateur | [{"id","type","street","city","country"}] |
| POST | /addresses | Creer adresse | Donnees adresse -> Adresse creee |
| GET | /addresses/type/{type} | Filtrer par type | Adresses filtrees par type (livraison, facturation) |
| GET | /addresses/{id} | Obtenir details adresse | Objet adresse complet |
| PUT | /addresses/{id} | Mettre a jour adresse | Donnees adresse -> Adresse mise a jour |
| PATCH | /addresses/{id} | Mise a jour partielle | Donnees partielles -> Adresse mise a jour |
| DELETE | /addresses/{id} | Supprimer adresse | Message succes |
| POST | /addresses/{id}/set-default | Definir comme defaut | {"type"} -> Succes |

## Schema de Base de Donnees

**Tables** :

1. **addresses** - Adresses utilisateurs
   - id (PK)
   - user_id (FK)
   - type (enum : shipping, billing, both)
   - label (string - ex., "Maison", "Bureau")
   - first_name
   - last_name
   - company (nullable)
   - street_1
   - street_2 (nullable)
   - city
   - region_id (FK, nullable)
   - postal_code
   - country_id (FK)
   - phone
   - email (nullable)
   - is_default_shipping (boolean)
   - is_default_billing (boolean)
   - delivery_instructions (text, nullable)
   - timestamps

2. **countries** - Donnees reference pays
   - id (PK)
   - name
   - code (ISO 3166-1 alpha-2, unique)
   - code_3 (ISO 3166-1 alpha-3)
   - numeric_code (ISO 3166-1 numeric)
   - phone_code
   - currency_code
   - is_active (boolean)
   - timestamps

3. **regions** - Donnees etat/province/region
   - id (PK)
   - country_id (FK)
   - name
   - code (unique dans pays)
   - type (state, province, region, etc.)
   - is_active (boolean)
   - timestamps

**Relations** :
- Address -> User (appartient a)
- Address -> Country (appartient a)
- Address -> Region (appartient a, nullable)
- Country -> Regions (a plusieurs)

## Validation Adresse

**Regles Validation** :
- Champs requis : first_name, last_name, street_1, city, country
- Validation format code postal par pays
- Validation format numero telephone
- Region requise pour certains pays (US, CA, etc.)
- Limites longueur rue
- Validation encodage caracteres (UTF-8)

**Integration Service Validation** (Futur) :
- API Google Maps pour verification adresse
- Validation adresse USPS pour adresses US
- Autocompletion adresse temps reel

## Integration RabbitMQ

**Evenements Consommes** :
- `user.created` - Initialiser modele adresse par defaut
- `order.created` - Journaliser utilisation adresse pour analytique

**Evenements Publies** :
- `address.created` - Nouvelle adresse ajoutee
- `address.updated` - Adresse modifiee
- `address.deleted` - Adresse retiree
- `address.default.changed` - Adresse par defaut mise a jour

**Exemple Format Message** :
```json
{
  "event": "address.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "address_id": 123,
    "user_id": 456,
    "type": "shipping",
    "country": "US",
    "is_default": true
  }
}
```

## Variables d'Environnement

```bash
# Application
APP_NAME=addresses-service
APP_ENV=local
APP_PORT=8004

# Base de donnees
DB_CONNECTION=mysql
DB_HOST=addresses-mysql
DB_PORT=3306
DB_DATABASE=addresses_db
DB_USERNAME=addresses_user
DB_PASSWORD=addresses_password

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=addresses_exchange
RABBITMQ_QUEUE=addresses_queue

# URLs Services
AUTH_SERVICE_URL=http://auth-service:8000
```

## Deploiement

**Configuration Docker** :
```yaml
Service: addresses-service
Port Mapping: 8004:8000
Database: addresses-mysql (port 3311 externe)
Depends On: addresses-mysql, rabbitmq
Networks: e-commerce-network
Health Check: /health endpoint
```

**Ressources Kubernetes** :
- Deployment : 2 replicas
- CPU Request : 100m, Limit : 200m
- Memory Request : 256Mi, Limit : 512Mi
- Service Type : ClusterIP

**Configuration Health Check** :
- Liveness Probe : GET /health (intervalle 10s)
- Readiness Probe : Connectivite base de donnees
- Startup Probe : timeout 30s

## Optimisation Performance

**Strategie Mise en Cache** :
- Pays et regions mis en cache indefiniment (changent rarement)
- Adresses utilisateur mises en cache (TTL 5 min)
- Recherches adresse par defaut mises en cache par utilisateur

**Optimisation Base de Donnees** :
- Index sur : user_id, country_id, region_id
- Index composites pour recherches adresse par defaut
- Recherche plein texte sur rue, ville pour recherche adresse

## Surveillance et Observabilite

**Metriques a Suivre** :
- Taux creation adresse
- Adresses par utilisateur (moyenne)
- Frequence changement adresse par defaut
- Echecs validation

**Journalisation** :
- Operations CRUD adresses
- Changements adresse par defaut
- Erreurs validation

## Ameliorations Futures

- Verification adresse avec APIs externes
- Stockage geolocalisation et coordonnees
- Integration autocompletion adresse
- Modeles format adresse internationaux
- Detection et gestion boites postales
- Classification entreprise vs residentiel
- Notation qualite adresse
